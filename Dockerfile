##
## Stage: base — PHP-FPM with all required extensions
##
FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
        icu-dev \
        libxml2-dev \
        tidyhtml-dev \
        libpq-dev \
    && docker-php-ext-install -j$(nproc) \
        intl \
        dom \
        pdo_mysql \
        pdo_pgsql \
        tidy \
    && docker-php-ext-enable opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

##
## Stage: builder — install Composer dependencies & compile SCSS and AssetMapper assets
##
FROM base AS builder

RUN apk add --no-cache dart-sass-js

WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Placeholder env vars — console commands that run here don't need a live DB
ENV APP_ENV=prod \
    APP_SECRET=__buildtime__ \
    DATABASE_URL="postgresql://x:x@localhost/x?serverVersion=16"

RUN composer build-assets

##
## Stage: php — production PHP-FPM image
##
FROM base AS php

WORKDIR /var/www/html

COPY --from=builder --chown=www-data:www-data /app ./
COPY docker/php/php.ini     /usr/local/etc/php/conf.d/app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p var \
    && chown -R www-data:www-data var

VOLUME /var/www/html/var

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

##
## Stage: nginx — production web server with compiled static assets
##
FROM nginx:1.31-alpine AS nginx

COPY --from=builder /app/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

##
## Stage: cron — RSS/ATOM ingestion service (loops app:gather)
##
FROM php AS cron

RUN apk add --no-cache bash tini

COPY docker/cron/start.sh /usr/local/bin/cron-start

RUN chmod +x /usr/local/bin/cron-start

ENTRYPOINT ["/sbin/tini", "--"]
CMD ["cron-start"]
