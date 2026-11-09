#!/bin/sh
set -eu

cd /var/www/html
mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec docker-php-entrypoint "$@"
