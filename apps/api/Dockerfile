FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    default-mysql-client

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the application source into the image (Container Apps has no shared
# volume, so the app + vendor must be baked in).
COPY . /var/www

# Install production dependencies at build time so start-up is fast and does
# not depend on runtime network access or a shared vendor volume.
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-progress

# Expose port 9000 for PHP-FPM (the ACA deployment overrides CMD/args and runs
# `php artisan serve --port=8000` instead).
EXPOSE 9000

# Copy the entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set the entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]

# Start PHP-FPM
CMD ["php-fpm"]
