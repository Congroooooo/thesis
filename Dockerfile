# Use PHP-FPM with Alpine
FROM php:8.2-fpm-alpine

# Install system packages and PHP extensions
RUN apk add --no-cache \
    bash \
    curl \
    zip \
    libzip-dev \
    zlib-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        gd \
        dom \
        xml \
        simplexml \
        fileinfo

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy only composer files to cache dependencies
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copy the rest of the project
COPY . .

EXPOSE 3000