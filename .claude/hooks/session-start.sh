#!/bin/bash
# Prepara una sesión de Claude Code en la nube: dependencias PHP y Node,
# PostgreSQL y Redis locales, y .env. Solo corre en sesiones remotas.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(pwd)}"

# El contenedor corre como root: sin esto Composer desactiva el plugin de Pest
# y `pest` pierde sus plugins (arch, laravel).
export COMPOSER_ALLOW_SUPERUSER=1
echo 'export COMPOSER_ALLOW_SUPERUSER=1' >> "${CLAUDE_ENV_FILE:-/dev/null}"

# El proxy de la sesión no deja bajar zips de GitHub de repos ajenos.
# phpstan/phpstan solo se publica como zip, así que se siembra la caché de
# Composer clonando el tag por git (que sí pasa por el proxy).
sembrar_phpstan () {
  local datos version referencia url destino tmp
  datos=$(php -r '
    $lock = json_decode(file_get_contents("composer.lock"), true);
    foreach (array_merge($lock["packages"], $lock["packages-dev"]) as $p) {
      if ($p["name"] === "phpstan/phpstan") { echo $p["version"], " ", $p["dist"]["reference"], " ", $p["dist"]["url"]; }
    }')
  [ -z "$datos" ] && return 0
  read -r version referencia url <<< "$datos"
  destino="$(composer config cache-files-dir 2>/dev/null)/phpstan/phpstan/$(printf %s "$url" | sha1sum | cut -d' ' -f1).zip"
  [ -f "$destino" ] && return 0
  tmp=$(mktemp -d)
  git clone -q --depth 1 --branch "$version" https://github.com/phpstan/phpstan "$tmp/phpstan-$referencia" 2>/dev/null || { rm -rf "$tmp"; return 0; }
  rm -rf "$tmp/phpstan-$referencia/.git"
  mkdir -p "$(dirname "$destino")"
  (cd "$tmp" && php -r '
    $zip = new ZipArchive(); $zip->open($argv[1], ZipArchive::CREATE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[2], FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { $zip->addFile($f->getPathname(), $f->getPathname()); }
    $zip->close();' "$destino" "phpstan-$referencia")
  rm -rf "$tmp"
}

sembrar_phpstan
composer install --no-interaction --no-progress \
  || composer install --no-interaction --no-progress --prefer-source

# npm install corre el script "prepare", que activa los hooks de git (Husky).
npm install --no-audit --no-fund

# Playwright: el contenedor trae Chromium en otra ruta; no descargar navegadores.
if [ -x /opt/pw-browsers/chromium ]; then
  echo 'export PLAYWRIGHT_CHROMIUM_EXECUTABLE=/opt/pw-browsers/chromium' >> "${CLAUDE_ENV_FILE:-/dev/null}"
fi

# Servicios locales: PostgreSQL 16 (las pruebas corren sobre Postgres) y Redis.
service postgresql start >/dev/null
su postgres -c "psql -q -c \"ALTER USER postgres PASSWORD 'postgres';\"" >/dev/null
for db in fidelizacion fidelizacion_test; do
  su postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='$db'\"" | grep -q 1 \
    || su postgres -c "createdb $db"
done
redis-cli ping >/dev/null 2>&1 || redis-server --daemonize yes >/dev/null

if [ ! -f .env ]; then
  cp .env.example .env
  php artisan key:generate --no-interaction
fi
php artisan migrate --force --no-interaction
