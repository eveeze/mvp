FROM php:8.3-fpm

# Install dependencies sistem
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libicu-dev \
    ffmpeg

# Install ekstensi PHP yang dibutuhkan Laravel & Postgres
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Permission handling (opsional, untuk menghindari isu permission di Linux host)
RUN chown -R www-data:www-data /var/www