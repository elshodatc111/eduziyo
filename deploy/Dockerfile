# syntax=docker/dockerfile:1
# EDUZIYO — ishlab chiqarish tasviri. Kod tasvirga kiradi (o'zgarmas); doimiy ma'lumot faqat `storage` va `public/uploads` hajmlarida.

# ---- 1) Frontend (Vite + Tailwind) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY app ./app
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- 2) PHP paketlari ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

# ---- 3) Ilova (php-fpm; queue/scheduler/watchdog ham shu tasvirdan) ----
FROM php:8.3-fpm AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libonig-dev default-mysql-client tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath opcache pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-eduziyo.ini
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private bootstrap/cache public/uploads \
    && chown -R www-data:www-data storage bootstrap/cache public/uploads
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---- 4) Veb-server (Nginx): faqat ochiq fayllar ----
FROM nginx:1.27-alpine AS web
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
