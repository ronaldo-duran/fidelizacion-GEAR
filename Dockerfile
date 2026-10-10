# syntax=docker/dockerfile:1

# Imagen de producción para la plataforma de fidelización.
# Un solo artefacto sirve los tres procesos (web, Horizon, scheduler) que
# docker-compose levanta con comandos distintos.

# --- Etapa 1: assets del frontend (Vite + Tailwind v4) ---
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- Etapa 2: dependencias de PHP sin dev ---
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
COPY artisan ./artisan
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# --- Etapa 3: imagen final ---
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_pgsql \
    pgsql \
    intl \
    bcmath \
    zip \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan filament:upgrade \
    && chown -R www-data:www-data storage bootstrap/cache \
    && cp docker/php/opcache.ini "$PHP_INI_DIR/conf.d/opcache.ini"

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
