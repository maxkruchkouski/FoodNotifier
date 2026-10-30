FROM php:8.4-fpm-alpine

# Устанавливаем системные зависимости для PostgreSQL и работы с графикой (нужно для вашего QR-генератора)
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    libzip-dev \
    unzip \
    git \
    bash

# Устанавливаем расширения PHP (pdo_pgsql, gd для QR, zip)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd zip

# Ставим Composer внутри контейнера
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Запускаем стандартный PHP-FPM
CMD ["php-fpm"]
