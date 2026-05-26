FROM php:7.4-apache

# Install system deps + PHP extensions in one layer
RUN apt-get update && apt-get install -y libzip-dev zip libpng-dev libjpeg-dev libfreetype6-dev curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer.json first so this layer is cached unless deps change
COPY composer.json .
RUN composer install --no-dev --optimize-autoloader

# Sarabun Thai font for mPDF — bundled via src/fonts/ (COPY src/ . below)
COPY src/ .
