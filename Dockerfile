FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y gettext-base && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    && composer dump-autoload --optimize --no-dev

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

