#!/bin/sh
set -e

# Cachés de framework en cada arranque: tras un despliegue el código cambió,
# así que se regeneran para que config, rutas, vistas y eventos queden al día.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Los artisan de arriba corren como root; se devuelve la propiedad a www-data
# para que php-fpm pueda escribir logs y cachés después.
chown -R www-data:www-data storage bootstrap/cache

# Las migraciones NO corren solas por defecto: son un paso de despliegue
# explícito (ver docs/despliegue.md). Con AUTO_MIGRATE=true solo el contenedor
# web (CMD supervisord) las aplica al arrancar, útil en staging; horizon y
# scheduler nunca migran para no correr migraciones en paralelo.
if [ "${AUTO_MIGRATE:-false}" = "true" ] && [ "$1" = "supervisord" ]; then
    php artisan migrate --force --isolated
fi

exec "$@"
