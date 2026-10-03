#!/bin/sh
# Runs once when the dev container is created: installs dependencies, writes
# backend/.env (generated database password, application key, and the URL the
# browser will use — the forwarded Codespaces URL when running in Codespaces),
# prepares the database, and builds the SPA so `php artisan serve` serves it.
set -e
cd /workspace

secret=/run/lcf-secrets/db_password
i=0
while [ ! -s "$secret" ] && [ "$i" -lt 120 ]; do sleep 1; i=$((i + 1)); done
[ -s "$secret" ] || { echo "The MySQL password was not generated ($secret)." >&2; exit 1; }

cd backend
composer install --no-interaction --no-progress
[ -f .env ] || cp .env.example .env

set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s#^$1=.*#$1=$2#" .env
    else
        printf '%s=%s\n' "$1" "$2" >> .env
    fi
}

url=http://localhost:8000
stateful=localhost:8000,127.0.0.1:8000
if [ -n "${CODESPACE_NAME:-}" ]; then
    host="${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
    url="https://${host}"
    stateful="${host},${stateful}"
    set_env SESSION_SECURE_COOKIE true
fi
set_env APP_ENV local
set_env APP_URL "$url"
set_env SANCTUM_STATEFUL_DOMAINS "$stateful"
set_env DB_CONNECTION mysql
set_env DB_HOST mysql
set_env DB_PORT 3306
set_env DB_DATABASE lcf
set_env DB_USERNAME root
set_env DB_PASSWORD "$(cat "$secret")"
set_env QUEUE_CONNECTION sync
set_env ERROR_SINK_PATH /workspace/backend/storage/logs/errors/errors.log
set_env FILES_ROOT /workspace/backend/storage/app/files
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force --no-interaction

php artisan db:ensure --no-interaction
php artisan migrate --seed --force --no-interaction
php artisan cache:clear --no-interaction

cd ../frontend
npm ci --no-audit --no-fund
npm run build

echo "Dev container ready. Application: ${url}"
echo "Setup token: cd backend && php artisan setup:token"
