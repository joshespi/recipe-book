#!/bin/bash
# Restores a backup made by backup.sh (database + photos). Run inside the app container:
#   docker compose exec app bash scripts/restore.sh /backups/recipes-YYYYmmdd-HHMMSS.tar.gz
#   docker compose exec app bash scripts/restore.sh --yes /backups/recipes-....tar.gz   # no prompt
#
# This REPLACES the current database contents and all photos.
# A safety backup of the current state is taken first (pre-restore-*.tar.gz).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
ASSUME_YES=0
if [ "${1:-}" = "--yes" ]; then
    ASSUME_YES=1
    shift
fi

archive="${1:-}"
if [ -z "$archive" ]; then
    echo "Usage: restore.sh [--yes] <archive.tar.gz>" >&2
    exit 2
fi
[ -f "$archive" ] || { echo "[restore] no such file: $archive" >&2; exit 1; }

export MYSQL_PWD="${DB_PASSWORD:?DB_PASSWORD is not set}"
DB_ARGS=(-h "${DB_HOST:?DB_HOST is not set}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:?DB_USERNAME is not set}")

work="$(mktemp -d)"
site_down=0
cleanup() {
    status=$?
    if [ "$site_down" = 1 ]; then
        (cd "$APP_DIR" && php artisan up) || true
    fi
    rm -rf "$work"
    exit "$status"
}
trap cleanup EXIT

# Check the archive before touching anything live.
echo "[restore] checking $archive"
tar -xzf "$archive" -C "$work"
for f in manifest.json database.sql; do
    [ -f "$work/$f" ] || { echo "[restore] archive is missing $f - not a backup from backup.sh" >&2; exit 1; }
done
echo "[restore] manifest:"
cat "$work/manifest.json"

if [ "$ASSUME_YES" != 1 ]; then
    echo
    echo "This replaces ALL recipes, categories, users and photos with the contents of this backup."
    read -r -p "Type RESTORE to continue: " answer
    [ "$answer" = "RESTORE" ] || { echo "[restore] cancelled"; exit 1; }
fi

echo "[restore] taking a safety backup of the current state"
bash "$APP_DIR/scripts/backup.sh" pre-restore

cd "$APP_DIR"
php artisan down --retry=60
site_down=1

echo "[restore] importing database"
mariadb "${DB_ARGS[@]}" "$DB_DATABASE" < "$work/database.sql"

echo "[restore] restoring photos"
target="$APP_DIR/storage/app/public/photos"
rm -rf "$target"
mkdir -p "$target"
if [ -d "$work/photos" ]; then
    cp -a "$work/photos/." "$target/"
fi
if [ "$(id -u)" = 0 ]; then
    chown -R www-data:www-data "$APP_DIR/storage/app/public"
fi

echo "[restore] migrating and clearing caches"
php artisan migrate --force
php artisan optimize:clear

echo "[restore] done"
