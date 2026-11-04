# syntax=docker/dockerfile:1.7

# Frontend assets.
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json tsconfig.app.json tsconfig.node.json components.json ./
RUN npm run build

# PHP dependencies.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --no-autoloader
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan composer.json composer.lock ./
RUN composer dump-autoload --optimize --no-dev --no-scripts

# Runtime.
FROM php:8.5-fpm-alpine AS runtime

RUN set -eux; \
    docker-php-ext-install pdo_mysql; \
    docker-php-ext-install bcmath; \
    docker-php-ext-install opcache || true; \
    php -m | grep -qx 'pdo_mysql'; \
    php -m | grep -qx 'bcmath'; \
    php -m | grep -q 'Zend OPcache'

WORKDIR /var/www/html

RUN addgroup -g 1000 its \
    && adduser -u 1000 -G its -D -s /sbin/nologin its

COPY --from=vendor --chown=its:its /app /var/www/html
COPY --from=assets --chown=its:its /app/public/build /var/www/html/public/build
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/99-its.ini
COPY .docker/php/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R its:its storage bootstrap/cache

USER its

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# Development tooling (not in the production image).
FROM runtime AS dev
USER root
RUN apk add --no-cache nodejs npm git
USER its

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
