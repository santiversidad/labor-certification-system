#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs

chown -R www-data:www-data bootstrap/cache storage

if [ "${APP_ENV:-local}" != "production" ] && grep -Eq '^APP_KEY=[[:space:]]*$' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan security:check-config --accounts --no-interaction
fi

# The file cache is bind-mounted with the application and can outlive a
# database restore/reseed. Spatie caches numeric role/permission IDs, so a
# cache created against an older database must never be reused at boot.
php artisan permission:cache-reset --no-interaction

exec "$@"
