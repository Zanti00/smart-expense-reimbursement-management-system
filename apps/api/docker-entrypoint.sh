#!/bin/bash
set -e

# Use environment variables with fallbacks
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}

# Wait for database to be ready
echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."
until mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" --silent; do
  echo "Database (${DB_HOST}) is not available yet - sleeping"
  sleep 1
done
echo "Database is ready!"

# Ensure dependencies are up-to-date
if [ ! -d "vendor" ]; then
  echo "Installing dependencies..."
  composer install --no-interaction --optimize-autoloader
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

echo "Running database migrations..."
php artisan migrate --force

# Start the main process (e.g., PHP-FPM or serve command)
exec "$@"
