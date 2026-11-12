FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libonig-dev libxml2-dev && \
    docker-php-ext-install pdo_mysql pdo_sqlite mbstring intl zip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress && mkdir -p storage/framework/{cache/data,sessions,views} && chmod -R 775 storage bootstrap/cache
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
