# syntax=docker/dockerfile:1.6
#
# Single-stage local-iteration image. Optimised for "fresh clone just
# works", NOT for production. No multi-stage builds, no opcache tuning,
# no route/config caching — just enough to run the app and tests.
#
FROM php:8.3-fpm-alpine

# System deps + PHP extensions
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        mbstring \
        bcmath \
        gd \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /tmp/pear /var/cache/apk/*

# Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer needs an explicit, writable cache path when running as root.
# Without these, modern composer aborts with "Please provide a valid
# cache path", which is the failure that previously broke fresh builds.
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# Copy the entire app source. Required Laravel runtime directories are
# created BEFORE any composer or artisan command so post-install scripts
# (which touch bootstrap/cache and storage/) cannot fail on a fresh
# clone where those folders are gitignored.
COPY src/ /var/www/html/

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/attachments \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Install ALL dependencies (dev included so phpunit ships in the image).
# `--no-scripts` skips the package:discover post-install hook during the
# install phase — we run it explicitly after dump-autoload so any
# failure surfaces loudly instead of aborting the layer.
RUN composer install --prefer-dist --no-scripts --no-autoloader --no-interaction \
    && composer dump-autoload --no-interaction \
    && chown -R www-data:www-data vendor storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PCOV_ENABLED=1
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
