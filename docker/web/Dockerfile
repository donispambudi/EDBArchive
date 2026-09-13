FROM node:22-bookworm-slim AS frontend

WORKDIR /build

COPY web/package.json ./
RUN npm install --no-audit --no-fund

COPY web/resources ./resources
COPY web/vite.config.js ./
RUN npm run build


FROM composer:2.8 AS vendor

WORKDIR /build

COPY web/composer.json web/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist


FROM php:8.4-apache-bookworm

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY web ./
COPY --from=vendor /build/vendor ./vendor
COPY --from=frontend /build/public/build ./public/build

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

CMD ["sh", "-c", "chown -R www-data:www-data storage/app && php artisan migrate --force && php artisan db:seed --force && exec apache2-foreground"]
