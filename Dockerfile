# syntax=docker/dockerfile:1

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


FROM node:24-alpine AS frontend

WORKDIR /app

ARG VITE_APP_NAME=Laravel
ENV VITE_APP_NAME=$VITE_APP_NAME

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY . .
COPY --from=backend /app/vendor /app/vendor
RUN npm run build:ssr \
    && test -f bootstrap/ssr/ssr.js \
    && test -d public/build


FROM dunglas/frankenphp:1-php8.4-alpine AS production

RUN install-php-extensions \
    bcmath \
    intl \
    opcache \
    pcntl \
    pdo_pgsql \
    zip \
    && apk add --no-cache wget curl \
    && mkdir -p /tmp/caddy/config /tmp/caddy/data \
    && printf '%s\n' \
        '; Long LLM SSE streams (Coolify / FrankenPHP).' \
        'max_execution_time = 0' \
        'default_socket_timeout = 600' \
        > /usr/local/etc/php/conf.d/zz-llms-stream.ini

WORKDIR /app

COPY --from=backend /app /app
COPY --from=frontend /app/public/build /app/public/build
COPY --from=frontend /app/bootstrap/ssr /app/bootstrap/ssr

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && cat > /etc/caddy/Caddyfile <<'EOF'
{
    auto_https off
    admin off
    frankenphp
}

:80 {
    root * /app/public

    # SSE must not be compressed — encode buffers the full response.
    handle /chat/stream* {
        header X-Accel-Buffering "no"
        php_server
    }

    handle {
        encode zstd br gzip
        php_server
    }
}
EOF

COPY <<'EOF' /usr/local/bin/production-entrypoint
#!/bin/sh
set -eu

cd /app

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    echo "FATAL: APP_KEY is empty. Set it in Coolify environment." >&2
    exit 1
fi

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

EXPOSE 80

ENTRYPOINT ["production-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]


FROM node:24-alpine AS ssr

WORKDIR /app

COPY --from=frontend /app/package.json /app/package-lock.json ./
COPY --from=frontend /app/node_modules ./node_modules
COPY --from=frontend /app/bootstrap/ssr ./bootstrap/ssr

ENV NODE_ENV=production

EXPOSE 13714

CMD ["node", "bootstrap/ssr/ssr.js"]
