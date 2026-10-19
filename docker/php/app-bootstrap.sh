#!/bin/bash
# Brings ./src up to date: PHP deps, schema, assets, caches, storage link, permissions.
# Idempotent. Runs from entrypoint.sh on every start (with --boot) and on deploys.
set -e

cd /var/www/html

if [ "${APP_ENV}" = "production" ]; then
    echo "[deploy] composer install (production)"
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
else
    echo "[deploy] composer install (dev deps included - APP_ENV=${APP_ENV:-unset})"
    composer install --optimize-autoloader --no-interaction --quiet
fi

echo "[deploy] migrate"
php artisan migrate --force

# Build beside the live copy and swap, so php-fpm never runs without a manifest.
build_assets() {
    local deps_stamp
    deps_stamp="$(node -v) $(sha1sum package-lock.json | cut -d' ' -f1)" || return 1
    if [ "$(cat node_modules/.deps-stamp 2>/dev/null)" != "$deps_stamp" ]; then
        echo "[deploy] npm ci"
        npm ci --no-audit --no-fund --loglevel=error || return 1
        echo "$deps_stamp" > node_modules/.deps-stamp || return 1
    fi

    echo "[deploy] npm run build"
    rm -rf public/build.next public/build.prev
    npm run build --silent -- --outDir public/build.next || return 1
    if [ -d public/build ]; then mv public/build public/build.prev || return 1; fi
    mv public/build.next public/build || return 1
    rm -rf public/build.prev
}

if ! build_assets; then
    if [ "${1:-}" = "--boot" ] && [ -f public/build/manifest.json ]; then
        echo "[deploy] WARNING: asset build failed - serving the previous build"
    else
        exit 1
    fi
fi

if [ "${APP_ENV}" = "production" ]; then
    echo "[deploy] optimize"
    php artisan optimize
else
    echo "[deploy] optimize:clear (APP_ENV=${APP_ENV:-unset})"
    php artisan optimize:clear
fi

echo "[deploy] storage:link"
php artisan storage:link --force >/dev/null

# Everything above runs as root; php-fpm/queue/scheduler run as www-data.
echo "[deploy] fixing storage/bootstrap permissions for www-data"
chown -R www-data:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} +
find storage bootstrap/cache -type f -exec chmod 664 {} +
