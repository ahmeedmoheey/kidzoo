FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libsqlite3-dev sqlite3 libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=vendor /app/vendor /app/vendor
COPY . /app

RUN chmod +x /app/render-start.sh \
    && mkdir -p /app/database /app/storage/framework/cache /app/storage/framework/sessions /app/storage/framework/views \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache /app/database

EXPOSE 10000

CMD ["/app/render-start.sh"]
