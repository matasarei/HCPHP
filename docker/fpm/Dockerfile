FROM php:7.0-fpm-alpine

WORKDIR /var/www/html

RUN docker-php-ext-install mysqli && \
    docker-php-ext-install pdo_mysql