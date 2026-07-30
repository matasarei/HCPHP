# The code still supports PHP 7.4, because the projects that copy from it are on 7.4.
# The container runs a supported release: 7.4 has been end of life since November 2022.
FROM php:8.3-fpm-alpine

RUN apk update && apk add --no-cache mysql-client zip libzip-dev git \
    && docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
