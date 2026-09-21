FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-install bcmath intl mbstring pdo_mysql zip \
    && a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

WORKDIR /var/www/html

COPY --from=dependencies /app /var/www/html

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 8080

# Cloud Run supplies PORT at runtime. Keep Apache bound to that port so the
# container works on Cloud Run as well as local/Render environments.
CMD ["sh", "-c", "PORT=${PORT:-8080}; sed -ri -e \"s/^Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf; sed -ri -e \"s/:80>/:${PORT}>/g\" /etc/apache2/sites-available/000-default.conf; php artisan storage:link --force || true; apache2-foreground"]
