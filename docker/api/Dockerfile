FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        curl \
        ca-certificates \
        unzip \
        procps \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libicu-dev \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-eng \
        tesseract-ocr-msa \
        gdal-bin \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd intl bcmath zip opcache pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/supervisor /var/lib/nginx/body /var/lib/nginx/fastcgi /run \
    && chown -R www-data:www-data /var/lib/nginx

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

COPY docker/api/php.ini /usr/local/etc/php/conf.d/sisdata.ini
COPY docker/api/zz-www.conf /usr/local/etc/php-fpm.d/zz-sisdata.conf
COPY docker/api/nginx.conf /etc/nginx/nginx.conf
COPY docker/api/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/api/entrypoint.sh /entrypoint.sh

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts \
    && sed -i 's/\r$//' /entrypoint.sh \
    && chmod +x /entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
CMD ["web"]
