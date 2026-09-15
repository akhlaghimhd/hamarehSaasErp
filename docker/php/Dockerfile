FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    $PHPIZE_DEPS \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd opcache \
    && pecl install redis \
    && docker-php-ext-enable redis opcache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php-perf.ini /usr/local/etc/php/conf.d/php-perf.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Development server (override command in compose if needed)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
