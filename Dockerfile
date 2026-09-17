FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip gd exif intl mbstring bcmath opcache

RUN a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
    && composer dump-autoload --optimize --no-dev

COPY . /var/www/html/

RUN mkdir -p storage/cache/shipping storage/comprovantes storage/logs assets/img/banners assets/img/products avatars \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/assets/img /var/www/html/avatars \
    && find /var/www/html/storage -type d -exec chmod 775 {} \; \
    && find /var/www/html/assets/img -type d -exec chmod 775 {} \;

COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite 000-default.conf