#!/bin/sh
# Container entrypoint: the "app" role prepares the database (creates it when
# missing, migrates, seeds), caches configuration and, while the system is not
# yet set up, prints the one-time setup token to the logs.
set -e
cd /var/www/backend

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is not set in backend/.env. Generate one with:" >&2
    echo "  docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show" >&2
    echo "and paste the printed value after APP_KEY= in backend/.env." >&2
    exit 1
fi

if [ "${LCF_ROLE:-app}" = "app" ]; then
    php artisan db:ensure --no-interaction
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    if php artisan setup:token --no-ansi > /tmp/setup-token 2>/dev/null; then
        echo "================================================================"
        echo " First-run setup: open the application and enter this token:"
        tail -n 1 /tmp/setup-token
        echo "================================================================"
    fi
    rm -f /tmp/setup-token
fi

exec "$@"
