# syntax=docker/dockerfile:1

FROM node:24-alpine AS frontend

WORKDIR /app

ARG VITE_APP_NAME=Laravel
ENV VITE_APP_NAME=$VITE_APP_NAME

COPY package*.json ./
RUN npm install --ignore-scripts

COPY . .
RUN npm run build


FROM composer:2 AS backend

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . .
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction


FROM dunglas/frankenphp:1-php8.4-alpine AS production

RUN install-php-extensions \
    bcmath \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    zip \
    && apk add --no-cache libcap-utils \
    && setcap cap_net_bind_service=+ep /usr/local/bin/frankenphp

WORKDIR /app

COPY --from=backend --chown=www-data:www-data /app /app
COPY --from=frontend --chown=www-data:www-data /app/public/build /app/public/build

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && cat > /etc/caddy/Caddyfile <<'EOF'
{
    auto_https off
    admin off
    frankenphp
}

:80 {
    root * /app/public
    encode zstd br gzip
    php_server
}
EOF

COPY <<'EOF' /usr/local/bin/production-entrypoint
#!/bin/sh
set -eu

php artisan storage:link --force --no-interaction
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
EOF
RUN chmod +x /usr/local/bin/production-entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data

USER www-data

EXPOSE 80

ENTRYPOINT ["production-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
