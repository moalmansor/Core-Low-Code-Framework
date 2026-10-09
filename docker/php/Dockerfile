# syntax=docker/dockerfile:1.7
# Images: "app" (php-fpm; also runs the queue worker and the scheduler), "web"
# (nginx with the built public directory), and "dev" (the dev container).

FROM node:22-bookworm-slim AS frontend
WORKDIR /src
COPY frontend/package.json frontend/package-lock.json frontend/
RUN cd frontend && npm ci --no-audit --no-fund
COPY frontend frontend
COPY backend/resources/ui-strings backend/resources/ui-strings
COPY backend/resources/calendars backend/resources/calendars
RUN mkdir -p backend/public && cd frontend && NODE_ENV=production npx vite build

FROM php:8.3-fpm-bookworm AS base
ARG ACCEPT_EULA=Y
# System libraries, the Microsoft ODBC driver for SQL Server, and PHP extensions.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends gnupg curl ca-certificates unzip git libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev libldap2-dev unixodbc-dev; \
    curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/microsoft.gpg; \
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list; \
    apt-get update; \
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18; \
    docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd bcmath pcntl ldap opcache exif; \
    pecl install redis sqlsrv pdo_sqlsrv; \
    docker-php-ext-enable redis sqlsrv pdo_sqlsrv; \
    apt-get purge -y --auto-remove gnupg; \
    rm -rf /var/lib/apt/lists/* /tmp/pear
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-lcf.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/backend

FROM base AS dev
# Development tooling for the dev container: Node for the SPA, mysql/redis clients.
COPY --from=frontend /usr/local/bin/node /usr/local/bin/node
COPY --from=frontend /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && apt-get update && apt-get install -y --no-install-recommends default-mysql-client redis-tools && rm -rf /var/lib/apt/lists/*
WORKDIR /workspace

FROM base AS app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY backend ./
COPY docs/architecture.md /var/www/docs/architecture.md
COPY --from=frontend /src/backend/public/build public/build
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs/errors storage/app/files storage/app/private storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/php/entrypoint.sh /usr/local/bin/lcf-entrypoint
# Strip carriage returns: a script checked out with CRLF (Windows without the
# repository's .gitattributes) would otherwise fail with "no such file or
# directory" because the kernel looks for the interpreter "/bin/sh\r" (ADR-0026).
RUN sed -i 's/\r$//' /usr/local/bin/lcf-entrypoint && chmod +x /usr/local/bin/lcf-entrypoint
USER www-data
ENTRYPOINT ["lcf-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS web
# The public directory comes from the same build as the app image, so the
# assets nginx serves always match the manifest the application reads.
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/backend/public /var/www/backend/public
