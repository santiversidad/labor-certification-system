FROM node:24-alpine AS backend-assets

WORKDIR /app

COPY backend/laravel-app/package.json backend/laravel-app/package-lock.json backend/laravel-app/.npmrc ./
RUN npm ci

COPY backend/laravel-app/resources ./resources
COPY backend/laravel-app/public ./public
COPY backend/laravel-app/vite.config.js ./
RUN npm run build

FROM composer:2.9 AS backend-dependencies

WORKDIR /app

COPY backend/laravel-app/composer.json backend/laravel-app/composer.lock ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --no-autoloader

COPY backend/laravel-app ./
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libonig-dev \
        libpq-dev \
        libpq5 \
        libxml2-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        dom \
        mbstring \
        pdo_pgsql \
        xml \
        xmlwriter \
    && apt-get purge -y --auto-remove libonig-dev libpq-dev libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=composer:2.9 /usr/bin/composer /usr/local/bin/composer
COPY --from=backend-dependencies --chown=www-data:www-data /app ./
COPY --from=backend-assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
