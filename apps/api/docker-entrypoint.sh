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
mkdir -p vendor bootstrap/cache storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs
chown -R www-data:www-data bootstrap/cache storage
chmod -R ug+rw bootstrap/cache storage

if [ "$SERMS_SERVICE_ROLE" = "worker" ]; then
  echo "Worker preparing its own dependencies/caches..."
  if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
  fi
  if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
  fi
  if ! grep -q "^APP_KEY=base64:" .env; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force
  fi
  if [ ! -f "bootstrap/cache/config.php" ]; then
    php artisan config:cache
  fi
  if [ ! -f "bootstrap/cache/routes-v7.php" ]; then
    php artisan route:cache
  fi
  exec "$@"
fi

# Ensure stale startup locks are cleared
rm -rf "$STARTUP_LOCK_DIR" 2>/dev/null || true

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

# Start the main process (e.g., PHP-FPM or serve command)
exec "$@"
