#!/usr/bin/env bash
# Se ejecuta EN el servidor compartido, recibido por SSH desde el workflow
# "Actualizar servidor". Actualiza el clon del repo de producción y refresca las
# cachés de Laravel. No compila nada: el código ya llega compilado y con vendor/.
#
# Variables que recibe por el entorno del SSH:
#   DEPLOY_PATH  ruta del clon en el servidor (obligatoria)
#   PHP_BIN      binario de PHP (opcional; por defecto "php")

set -euo pipefail

: "${DEPLOY_PATH:?Falta DEPLOY_PATH}"
PHP_BIN="${PHP_BIN:-php}"

cd "$DEPLOY_PATH"

"$PHP_BIN" artisan down --retry=15 || true

# El servidor recibe el código ya compilado. reset --hard deja el clon idéntico
# a origin/main sin tocar .env ni storage/ (que no están versionados).
git fetch origin main
git reset --hard origin/main

"$PHP_BIN" artisan migrate --force --no-interaction
"$PHP_BIN" artisan storage:link || true

# Cachés de producción: config, rutas, vistas y eventos, más las de Filament.
"$PHP_BIN" artisan optimize
"$PHP_BIN" artisan filament:optimize || true

# Que los procesos de cola recarguen el código nuevo (cola por base de datos).
"$PHP_BIN" artisan queue:restart || true

"$PHP_BIN" artisan up
