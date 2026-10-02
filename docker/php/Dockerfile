# syntax=docker/dockerfile:1
#
# Stages:
#   base    PHP-FPM runtime with the extensions, Chromium and Node -- shared
#   dev     base + Composer and a user matching the host uid (compose.yaml)
#   prod    base + the application baked in, no dev dependencies (compose.prod.yaml)
#   web     nginx with public/ and the built assets (compose.prod.yaml)

FROM php:8.4-fpm-alpine AS base

# Build dependencies are removed again after the extensions are compiled; only
# the shared libraries the extensions actually link against stay installed.
# icu-data-full: Alpine's icu-libs only carries English locale data.
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpq-dev \
        linux-headers \
 && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
 && runDeps="$(scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
        | tr ',' '\n' | sort -u \
        | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }')" \
 && apk add --no-cache $runDeps icu-data-full \
 && apk del .build-deps

# Argon2id is compiled into the official images; fail the build loudly if it is not.
RUN php -r 'exit(defined("PASSWORD_ARGON2ID") ? 0 : 1);' \
    || (echo "PASSWORD_ARGON2ID missing in this PHP build" && exit 1)

# Preview thumbnails: scripts/preview-screenshot.mjs drives Chromium through
# playwright-core. Playwright's own browser builds do not run on Alpine, so the
# distribution's Chromium is used instead; config('previews.thumbnails.chromium')
# points at it. Noto covers the fonts a preview does not bring itself.
RUN apk add --no-cache \
        bash \
        chromium \
        font-noto \
        nodejs

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-smallgate.ini

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
FROM base AS dev

RUN apk add --no-cache \
        git \
        postgresql-client

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

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

# ---------------------------------------------------------------------------
# Production dependencies, resolved strictly from composer.lock. Scripts and
# the autoloader wait until the application code is in place.
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN mkdir -p bootstrap/cache
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# ---------------------------------------------------------------------------
# Frontend build and the one runtime package the worker needs (playwright-core).
FROM node:24-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Tailwind scans these for class names.
COPY vite.config.js ./
COPY resources ./resources
COPY app ./app
RUN npm run build \
 && rm -rf node_modules \
 && npm ci --omit=dev --omit=optional --no-audit --no-fund

# ---------------------------------------------------------------------------
FROM base AS prod

COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/99-zz-smallgate-prod.ini
COPY docker/php/entrypoint-prod.sh /usr/local/bin/smallgate-entrypoint

# Code is owned by root and read-only for the php-fpm user (www-data); only
# storage/ and bootstrap/cache/ are writable. storage/ is a volume in
# compose.prod.yaml and starts out with this layout.
COPY --from=vendor /var/www/html /var/www/html
COPY --from=assets /build/public/build /var/www/html/public/build
COPY --from=assets /build/node_modules /var/www/html/node_modules

RUN mkdir -p \
        storage/app/previews \
        storage/app/private \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x /usr/local/bin/smallgate-entrypoint

USER www-data

ENTRYPOINT ["smallgate-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# nginx needs only public/: static files directly, everything else goes to
# php-fpm in the app container.
FROM nginx:1.27-alpine AS web

COPY --from=prod /var/www/html/public /var/www/html/public
COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
