FROM php:7.4-fpm-alpine

RUN apk update && apk add mysql-client zip libzip-dev
RUN docker-php-ext-install pdo pdo_mysql zip

WORKDIR /var/www/html
