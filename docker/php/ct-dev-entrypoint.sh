#!/bin/sh
set -eu

# CT opt-in; normal/test/production entrypoints retain their existing behavior.
if [ "${CT_DEV_CONFIG_CACHE:-false}" = "true" ]; then
    if [ "${APP_ENV:-}" != "local" ]; then
        echo "CT development config cache cannot run outside APP_ENV=local." >&2
        exit 1
    fi
    mkdir -p "$(dirname "$APP_CONFIG_CACHE")"
    php /usr/local/bin/ct-dev-config.php refresh
    php /usr/local/bin/ct-dev-config.php watch &
elif [ "${APP_CONFIG_CACHE:-}" = "/var/www/html/storage/framework/cache/ct-dev-config.php" ]; then
    # Opt-out must actually restore uncached configuration on the next start.
    rm -f "$APP_CONFIG_CACHE"
fi

exec docker-entrypoint "$@"
