#!/bin/sh
set -e

# Cachés de framework en cada arranque: tras un despliegue el código cambió,
# así que se regeneran para que config, rutas, vistas y eventos queden al día.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Las migraciones NO corren solas por defecto: son un paso de despliegue
# explícito (ver docs/despliegue.md). Con AUTO_MIGRATE=true el contenedor web
# las aplica al arrancar, útil solo en staging.
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    php artisan migrate --force --isolated
fi

exec "$@"
