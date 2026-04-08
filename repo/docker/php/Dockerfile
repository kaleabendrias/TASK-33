# syntax=docker/dockerfile:1.6
#
# Single-stage local-iteration image. Optimised for "fresh clone just
# works" and CONSISTENCY across machines, NOT for build speed. Every
# step that historically failed silently is split out and verified
# explicitly so a broken image can never reach the test runner.
#
FROM php:8.3-fpm-alpine

# Make every shell step abort on first failure. Without this, an `&&`
# chain inside a RUN can mask a non-zero exit if the *last* command
# happens to succeed (the previously-broken pcov enable case).
SHELL ["/bin/sh", "-eux", "-c"]

# 1. System packages and core PHP extensions. Build toolchain stays in
#    the image — local iteration values reproducibility over image
#    size, and removing build deps was a recurring source of "works on
#    my machine" failures.
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        binutils \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        mbstring \
        bcmath \
        gd

# 2. PCOV (coverage driver). We compile and install the .so but
#    deliberately DO NOT register it via conf.d. The runtime test
#    script loads pcov via an explicit `php -d extension=/abs/path`
#    flag, which has proven to be the only loading mechanism that
#    works consistently across hosts (some servers' apk index hiccups
#    or seccomp profiles cause `docker-php-ext-enable` and conf.d
#    scanning to silently no-op even though the .so is present and
#    loadable). The build-time check verifies that PHP can ACTUALLY
#    dlopen the .so via the same -d mechanism the test runner uses,
#    so any image that reaches the registry can run the suite.
RUN pecl install pcov \
    && PHP_EXT_DIR="$(php -r 'echo ini_get("extension_dir");')" \
    && test -f "$PHP_EXT_DIR/pcov.so" \
    && php -d "extension=$PHP_EXT_DIR/pcov.so" \
           -r 'exit(extension_loaded("pcov") ? 0 : 1);' \
    && echo "pcov loadable via -d ✓"

# 3. Composer binary (single source of truth)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer needs an explicit, writable cache path when running as root.
# Without these, modern composer aborts with "Please provide a valid
# cache path" — the failure that previously broke fresh builds.
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# 4. Copy the application source. Required Laravel runtime directories
#    are created BEFORE any composer or artisan command so post-install
#    scripts (which touch bootstrap/cache and storage/) cannot fail on
#    a fresh clone where those folders are gitignored.
COPY src/ /var/www/html/

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/attachments \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# 5. Install ALL dependencies (dev included so phpunit ships in the
#    image). Two-step install: first download and link with
#    `--no-scripts --no-autoloader` so post-install hooks cannot crash
#    before autoload.php exists, then dump the autoloader once
#    everything is on disk.
RUN composer install --prefer-dist --no-scripts --no-autoloader --no-interaction \
    && composer dump-autoload --no-interaction \
    && chown -R www-data:www-data vendor storage bootstrap/cache

# 6. Build-time sanity gate. The image must contain phpunit and a
#    loadable pcov.so. Both are functional checks (test the file is
#    there, dlopen the extension via the same -d mechanism the test
#    runner uses), not config-discovery checks.
RUN test -x vendor/bin/phpunit \
    && PHP_EXT_DIR="$(php -r 'echo ini_get("extension_dir");')" \
    && test -f "$PHP_EXT_DIR/pcov.so" \
    && php -d "extension=$PHP_EXT_DIR/pcov.so" \
           -r 'exit(extension_loaded("pcov") ? 0 : 1);'

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV PCOV_ENABLED=1
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
