# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.4

# ---- PHP dependencies (no dev) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Octane's RoadRunner server needs these two packages; they are not in composer.json.
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs \
    && composer require spiral/roadrunner-http spiral/roadrunner-cli --no-interaction --no-scripts --update-no-dev --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---- Frontend assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
# Tailwind scans app/ for class names; vendor is needed by some Blade/Livewire packages.
COPY app ./app
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- RoadRunner binary ----
FROM ghcr.io/roadrunner-server/roadrunner:2025 AS roadrunner

# ---- Runtime ----
FROM php:${PHP_VERSION}-cli-bookworm AS runtime

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pdo_mysql redis pcntl sockets intl zip opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && apt-get clean

RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=1'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.max_accelerated_files=20000'; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY --from=roadrunner /usr/bin/rr /var/www/html/rr
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint /var/www/html/rr \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8000
ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]
