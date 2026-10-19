FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl zip unzip tar gzip mariadb-client \
    libonig-dev libzip-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# GD with JPEG/WebP/FreeType: photo processing (intervention/image) needs it.
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring pcntl bcmath gd exif zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node lives in this image so assets build with `docker compose exec app npm run build`.
# trixie-slim matches php:8.5-fpm's Debian base (glibc), so native vite packages run here.
COPY --from=node:24.18-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24.18-trixie-slim /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY docker/php/zzz-laravel.conf /usr/local/etc/php-fpm.d/zzz-laravel.conf
COPY docker/php/zz-app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/app-bootstrap.sh /usr/local/bin/app-bootstrap
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh /usr/local/bin/app-bootstrap

WORKDIR /var/www/html

ENTRYPOINT ["/entrypoint.sh"]
