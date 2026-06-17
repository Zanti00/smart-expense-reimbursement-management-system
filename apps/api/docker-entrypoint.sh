#!/bin/bash
set -e

# Use environment variables with fallbacks
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}
SERMS_SERVICE_ROLE=${SERMS_SERVICE_ROLE:-api}
STARTUP_LOCK_DIR="bootstrap/cache/.startup-lock"

# Wait for database to be ready
echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" --silent; do
  echo "Database (${DB_HOST}) is not available yet - sleeping"
  sleep 1
done
echo "Database is ready!"

# Ensure dependency and cache directories exist inside Docker-managed volumes.
mkdir -p vendor bootstrap/cache storage/framework/cache/data storage/framework/views storage/framework/sessions
chmod -R ug+rw bootstrap/cache storage/framework/cache storage/framework/views storage/framework/sessions

if [ "$SERMS_SERVICE_ROLE" = "worker" ]; then
  echo "Waiting for API container to prepare Laravel dependencies/caches..."
  until [ -f "vendor/autoload.php" ] && [ -f "bootstrap/cache/config.php" ] && [ -f "bootstrap/cache/routes-v7.php" ]; do
    echo "Laravel dependencies/caches are not ready yet - sleeping"
    sleep 1
  done

  exec "$@"
fi

echo "Waiting for Laravel startup lock..."
until mkdir "$STARTUP_LOCK_DIR" 2>/dev/null; do
  echo "Another SERMS container is preparing dependencies/caches - sleeping"
  sleep 1
done
trap 'rmdir "$STARTUP_LOCK_DIR" 2>/dev/null || true' EXIT

# Ensure dependencies are installed in the Docker vendor volume.
if [ ! -f "vendor/autoload.php" ]; then
  echo "Installing dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
elif [ "composer.lock" -nt "vendor/autoload.php" ]; then
  echo "Composer lock changed; refreshing optimized autoloader..."
  composer dump-autoload --no-interaction --optimize
else
  echo "Composer dependencies are ready."
fi

# Ensure .env exists
if [ ! -f .env ]; then
  echo "Creating .env from .env.example..."
  cp .env.example .env
fi

# Ensure APP_KEY is generated
if ! grep -q "^APP_KEY=base64:" .env; then
  echo "Generating APP_KEY..."
  php artisan key:generate --force
fi

echo "Clearing stale Laravel caches..."
php artisan optimize:clear

echo "Running database migrations..."
php artisan migrate --force

echo "Building Laravel boot caches..."
php artisan config:cache
php artisan event:cache
php artisan route:cache

rmdir "$STARTUP_LOCK_DIR" 2>/dev/null || true
trap - EXIT

# Start the main process (e.g., PHP-FPM or serve command)
exec "$@"
