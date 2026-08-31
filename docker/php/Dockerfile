# syntax=docker/dockerfile:1
FROM php:8.4-fpm-alpine

# System packages required by the PHP extensions below.
RUN apk add --no-cache \
        bash \
        git \
        icu-dev \
        libpq-dev \
        libzip-dev \
        linux-headers \
        postgresql-client \
        $PHPIZE_DEPS

RUN docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql

# Argon2id is compiled into the official images; fail the build loudly if it is not.
RUN php -r 'exit(defined("PASSWORD_ARGON2ID") ? 0 : 1);' \
    || (echo "PASSWORD_ARGON2ID missing in this PHP build" && exit 1)

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-smallgate.ini

# Create a user whose uid matches the host user, so files written by artisan or
# composer inside the container stay editable on the host. php-fpm workers drop
# to this user; one-off CLI commands run as it via `docker compose run --user`.
ARG UID=1000
ARG GID=1000
RUN if ! getent group "${GID}" >/dev/null; then addgroup -g "${GID}" app; fi \
 && if ! getent passwd "${UID}" >/dev/null; then \
        adduser -u "${UID}" -G "$(getent group "${GID}" | cut -d: -f1)" -S -s /bin/bash app; \
    fi \
 && sed -i "s/^user = .*/user = $(getent passwd "${UID}" | cut -d: -f1)/; \
            s/^group = .*/group = $(getent group "${GID}" | cut -d: -f1)/" \
        /usr/local/etc/php-fpm.d/www.conf

# The bind-mounted repository is owned by the host user, which git would
# otherwise refuse to touch from inside the container.
RUN git config --system --add safe.directory /var/www/html

WORKDIR /var/www/html
