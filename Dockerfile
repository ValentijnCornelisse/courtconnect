FROM php:8.4-cli

WORKDIR /var/www/html

RUN php -r "if (PHP_VERSION_ID < 80401) { fwrite(STDERR, 'CourtConnect requires PHP 8.4.1 or higher.'.PHP_EOL); exit(1); }"

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        default-mysql-client \
    && docker-php-ext-install \
        bcmath \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY docker/entrypoint.sh /usr/local/bin/courtconnect-entrypoint

RUN chmod +x /usr/local/bin/courtconnect-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["courtconnect-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
