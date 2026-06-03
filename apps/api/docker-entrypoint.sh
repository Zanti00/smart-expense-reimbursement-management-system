#!/bin/bash
set -e

echo "Running database migrations..."
php artisan migrate --force

# Start the main process (e.g., PHP-FPM or serve command)
exec "$@"
