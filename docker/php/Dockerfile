# Используем легковесный и быстрый образ на базе Alpine Linux с PHP 8.4
FROM php:8.4-fpm-alpine

# Устанавливаем системные утилиты, необходимые для компиляции драйвера PostgreSQL
RUN apk add --no-cache postgresql-dev

# Встроенная утилита Docker устанавливает и активирует расширения pdo и pdo_pgsql
RUN docker-php-ext-install pdo pdo_pgsql

# Скачиваем последнюю стабильную версию Composer прямо из официального Docker-образа
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Задаем рабочую директорию внутри контейнера бэкенда
WORKDIR /var/www/backend
