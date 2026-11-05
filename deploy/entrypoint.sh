#!/bin/sh
# Konteyner ishga tushganda: papka huquqlari, storage:link va Laravel keshlari (env_file qiymatlari bilan).
set -e
cd /var/www/html

if [ "$(id -u)" = "0" ]; then
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private public/uploads
    chown -R www-data:www-data storage bootstrap/cache public/uploads 2>/dev/null || true
    # php-fpm root sifatida ishga tushadi (ishchilar www-data); boshqa xizmatlar `user: www-data` bilan keladi
fi

if [ "${EDU_SKIP_OPTIMIZE:-0}" != "1" ]; then
    [ -L public/storage ] || php artisan storage:link --force >/dev/null 2>&1 || true
    php artisan config:cache >/dev/null 2>&1 || true
    php artisan route:cache  >/dev/null 2>&1 || true
    php artisan view:cache   >/dev/null 2>&1 || true
    php artisan event:cache  >/dev/null 2>&1 || true
fi

exec "$@"
