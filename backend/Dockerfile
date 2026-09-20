# Laravel API image, built by Render from render.yaml.
#
# Apache + mod_php rather than nginx + php-fpm: one process, one port, no
# supervisor — which is exactly what a Render web service wants.
FROM php:8.4-apache

# pdo_pgsql because Render's managed database is PostgreSQL (locally the app
# runs on MySQL or SQLite; Laravel handles the difference).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Laravel's front controller lives in public/ and needs rewrites.
RUN a2enmod rewrite
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Dependencies first: editing application code then reuses this layer.
# --no-scripts, because the artisan scripts need the code that isn't here yet.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Render routes to $PORT; 10000 is its default and what entrypoint falls back to.
EXPOSE 10000

CMD ["entrypoint"]
