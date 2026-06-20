FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libzip-dev libicu-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql gd zip intl mbstring opcache

# phpredis for session + fragment cache
RUN pecl install redis && docker-php-ext-enable redis

# Apache config
RUN a2enmod rewrite headers expires deflate

# PHP production tuning
COPY docker/php.ini /usr/local/etc/php/conf.d/lifeline.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# App code
WORKDIR /var/www/html
COPY lifeline/ ./

# Uploads writable at runtime — use a volume mount in docker-compose
RUN mkdir -p uploads/profile_pics uploads/verification \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 uploads/

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/healthz || exit 1
