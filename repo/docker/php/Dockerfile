# syntax=docker/dockerfile:1.6
#
# Multi-stage build for the ServicePlatform PHP image.
#
#  base        — runtime PHP-FPM with extensions and php-fpm config
#  vendor      — composer install for production deps (layer-cached)
#  production  — minimal runtime image (default target)
#  development — adds dev composer deps + pcov + a pre-built Tailwind CSS asset,
#                ready to run tests immediately without runtime installs
#

# ───────────────────────────── BASE ─────────────────────────────
FROM php:8.3-fpm-alpine AS base

# System deps + PHP extensions in a single layer for caching
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        fcgi \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        mbstring \
        opcache \
        bcmath \
        gd \
    && rm -rf /var/cache/apk/*

# php-fpm-healthcheck
RUN echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck \
    && echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong' >> /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# PHP-FPM tuning
RUN echo '[global]' > /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'daemonize = no' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo '[www]' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'pm.status_path = /status' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && echo 'ping.response = pong' >> /usr/local/etc/php-fpm.d/zz-docker.conf

# OPcache tuning
RUN echo 'opcache.enable=1' > /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.memory_consumption=128' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.max_accelerated_files=10000' >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo 'opcache.validate_timestamps=1' >> /usr/local/etc/php/conf.d/opcache.ini

# Composer binary (single source of truth)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ──────────────────────────── VENDOR ────────────────────────────
# Cache production composer install on its own layer. Composer needs an
# explicit, writable cache path when running as root inside the build
# container — without HOME set, modern composer aborts with
# "Please provide a valid cache path". The previous version of this
# stage hid that error behind `2>/dev/null || true`, which silently
# produced an empty vendor/ and broke every downstream stage.
FROM base AS vendor

ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_ALLOW_SUPERUSER=1

COPY src/composer.json src/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ────────────────────────── PRODUCTION ──────────────────────────
FROM base AS production

# Composer needs an explicit, writable cache path when running as root
# inside the build container. Without HOME set, modern composer fails
# with "Please provide a valid cache path". Defining these env vars
# inside the image guarantees every RUN composer ... step works.
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=vendor /var/www/html/vendor ./vendor
COPY src/ .
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ───────────────────────── DEVELOPMENT ──────────────────────────
# Layered on top of base — adds dev composer deps, pcov, and a pre-built
# Tailwind CSS file. The test runner targets this stage so the test cycle
# never installs anything at runtime.
FROM base AS development

# Composer needs an explicit, writable cache path when running as root.
# Setting these BEFORE any composer step is the load-bearing fix for
# the "Please provide a valid cache path" failure that previously left
# vendor/ empty and broke `vendor/bin/phpunit` lookups at test time.
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_ALLOW_SUPERUSER=1

# Build toolchain for pecl extensions (separate layer; cacheable).
# Failures here MUST surface — silencing pecl install with `>/dev/null`
# previously hid pcov build errors and produced an image without the
# coverage driver, which run_tests.sh would then refuse to start.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

# Pre-bake composer dev dependencies into the image so `vendor/` exists
# inside the container BEFORE the bind-mount overrides it. The runtime
# entrypoint can fall back to a re-install only if the mount is empty.
#
# Critical: do NOT swallow stderr or use `|| true` here. A failed
# composer install must abort the build, not silently produce a broken
# image whose `vendor/bin/phpunit` is missing at test time.
COPY src/composer.json src/composer.lock* ./
RUN composer install --prefer-dist --no-scripts --no-autoloader --no-interaction

# Copy entire src tree so artisan etc. work in case the bind mount is
# absent (e.g. when running tests directly via `docker run`).
COPY src/ .
RUN composer dump-autoload --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

# Sanity check: phpunit MUST be present in the baked image. If this
# line ever fails, the composer install above silently dropped dev
# dependencies and the test runner would die later with a confusing
# "Could not open input file: vendor/bin/phpunit" error.
RUN test -x vendor/bin/phpunit || (echo "FATAL: phpunit was not installed" && exit 1)

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PCOV_ENABLED=1
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
