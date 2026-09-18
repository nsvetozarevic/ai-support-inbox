#!/bin/sh
set -e

cd /var/www/html

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

if [ ! -f .env.docker.local ]; then
    cp .env.docker.example .env.docker.local
fi

if [ ! -f .env.testing ]; then
    cp .env.testing.example .env.testing
fi

if [ -f .env.docker.local ] && [ -f .env.testing ] && grep -q '^APP_KEY=$' .env.testing 2>/dev/null; then
    app_key=$(grep '^APP_KEY=' .env.docker.local | cut -d= -f2-)
    if [ -n "$app_key" ]; then
        sed "s|^APP_KEY=.*|APP_KEY=$app_key|" .env.testing > .env.testing.tmp
        mv .env.testing.tmp .env.testing
    fi
fi

ln -sf .env.docker.local .env

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    unset APP_KEY
    php artisan key:generate --force --no-interaction
fi

if [ "$1" = "php-fpm" ]; then
    php artisan migrate --force --no-interaction
fi

exec docker-php-entrypoint "$@"
