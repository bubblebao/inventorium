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

# Download Sarabun Thai font for mPDF (used in PO PDF generation)
RUN mkdir -p /var/www/html/fonts && \
    curl -L -o /var/www/html/fonts/Sarabun-Regular.ttf "https://raw.githubusercontent.com/googlefonts/sarabun/main/fonts/ttf/Sarabun-Regular.ttf" && \
    curl -L -o /var/www/html/fonts/Sarabun-Bold.ttf "https://raw.githubusercontent.com/googlefonts/sarabun/main/fonts/ttf/Sarabun-Bold.ttf"

COPY src/ .
