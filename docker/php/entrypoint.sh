#!/bin/sh
# Container entrypoint: the "app" role migrates the database and, while the
# system is not yet set up, prints the one-time setup token to the logs.
set -e
cd /var/www/backend

if [ "${LCF_ROLE:-app}" = "app" ]; then
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
