#!/bin/sh
set -e

cd /var/www/html

# Install dependencies if vendor is missing (volume mount overrides image layer)
if [ ! -f vendor/autoload.php ]; then
    echo "📦 Installing Composer dependencies..."
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

# Ensure storage directories exist with correct permissions
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
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
