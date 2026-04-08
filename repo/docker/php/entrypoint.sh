#!/bin/sh
set -e

cd /var/www/html

# Composer needs an explicit, writable cache path when running as root.
export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer/cache}"
export COMPOSER_ALLOW_SUPERUSER=1
mkdir -p "$COMPOSER_CACHE_DIR"

# Always create the Laravel runtime directories BEFORE any composer or
# artisan command runs. These are gitignored, so on a fresh clone the
# bind-mounted ./src tree does not contain them and any post-install
# script that touches bootstrap/cache or storage/ would otherwise fail.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/attachments \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Install dependencies if vendor is missing (the bind mount overrides
# whatever was baked into the image). Use --no-scripts on the initial
# install so post-install hooks cannot crash before autoload exists,
# then dump autoload separately.
if [ ! -f vendor/autoload.php ] || [ ! -x vendor/bin/phpunit ]; then
    echo "Installing Composer dependencies..."
    composer install --prefer-dist --no-scripts --no-autoloader --no-interaction
    composer dump-autoload --no-interaction
fi

# Wait for the database to be ready
echo "Waiting for PostgreSQL..."
until php artisan db:monitor --databases=pgsql 2>/dev/null; do
    sleep 1
done
echo "PostgreSQL is ready"

# Run migrations and seed (idempotent)
echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "Running seeders..."
php artisan db:seed --force --no-interaction

# NOTE: route:cache and config:cache are intentionally omitted. They
# add fragility on local iteration (stale caches after edits) and buy
# nothing for dev. Production images should re-add them.

echo "Application ready"

exec "$@"
