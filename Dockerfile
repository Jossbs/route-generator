# syntax=docker/dockerfile:1
FROM php:8.5-fpm-bookworm AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev unzip \
    && docker-php-ext-install -j2 pdo_pgsql intl pcntl \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM php-base AS source
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative --no-interaction --no-scripts \
    && php artisan package:discover --ansi

FROM source AS frontend
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && npm ci --no-audit --no-fund \
    && npm run build

FROM php-base AS app
ARG VCS_REF=unknown
LABEL org.opencontainers.image.revision=$VCS_REF
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
COPY --from=source /var/www/html /var/www/html
COPY --from=frontend /var/www/html/public/build /var/www/html/public/build
COPY deploy/php.ini /usr/local/etc/php/conf.d/production.ini
COPY --chmod=755 deploy/entrypoint.sh /usr/local/bin/route-entrypoint
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["route-entrypoint"]
CMD ["php-fpm"]

FROM nginx:stable-alpine AS web
ARG VCS_REF=unknown
LABEL org.opencontainers.image.revision=$VCS_REF
COPY --from=app /var/www/html/public /var/www/html/public
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 8080
