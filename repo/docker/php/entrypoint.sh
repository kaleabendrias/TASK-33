#!/bin/sh
set -e

cd /var/www/html

# Composer needs an explicit, writable cache path when running as root
# inside the container. Without HOME/COMPOSER_HOME, composer aborts
# with "Please provide a valid cache path", which previously left the
# bind-mounted vendor/ empty and made `vendor/bin/phpunit` impossible
# to find at test time.
export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-/tmp/composer/cache}"
export COMPOSER_ALLOW_SUPERUSER=1
mkdir -p "$COMPOSER_CACHE_DIR"

# Install dependencies if vendor is missing (volume mount overrides image
# layer). In the development image (PCOV_ENABLED=1) we MUST install dev
# dependencies as well — otherwise phpunit is absent and the test runner
# crashes with "Could not open input file: vendor/bin/phpunit" the first
# time it executes against a fresh checkout where ./src/vendor was empty.
if [ ! -f vendor/autoload.php ] || { [ "${PCOV_ENABLED:-0}" = "1" ] && [ ! -x vendor/bin/phpunit ]; }; then
    if [ "${PCOV_ENABLED:-0}" = "1" ]; then
        echo "📦 Installing Composer dependencies (with dev)..."
        composer install --prefer-dist --optimize-autoloader --no-interaction
    else
        echo "📦 Installing Composer dependencies..."
        composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    fi
fi

# Ensure storage directories exist with correct permissions. These are
# intentionally NOT shipped in the build context (see .dockerignore)
# so the entrypoint is the single source of truth for the runtime tree.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/attachments \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Wait for database to be truly ready
echo "⏳ Waiting for PostgreSQL..."
until php artisan db:monitor --databases=pgsql 2>/dev/null; do
    sleep 1
done
echo "✅ PostgreSQL is ready"

# Run migrations
echo "🔄 Running migrations..."
php artisan migrate --force --no-interaction

# Run seeders (idempotent — uses firstOrCreate)
echo "🌱 Running seeders..."
php artisan db:seed --force --no-interaction

# Cache config for performance
php artisan config:cache
php artisan route:cache

echo "🚀 Application ready"

exec "$@"
