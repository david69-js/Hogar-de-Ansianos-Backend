#!/bin/bash
set -e

APP_DIR="/var/www/sorherminia"
ENV_FILE="${APP_DIR}/.env"
APP_PORT="${PORT:-8080}"

echo "Ensuring Apache uses a single MPM (prefork)..."
a2dismod mpm_event mpm_worker mpm_prefork >/dev/null 2>&1 || true
a2enmod mpm_prefork rewrite >/dev/null 2>&1 || true

echo "Configuring Apache to listen on port ${APP_PORT}..."
sed -i "s|PLACEHOLDER_PORT|${APP_PORT}|g" /etc/apache2/ports.conf
sed -i "s|<VirtualHost \*:[0-9]*>|<VirtualHost *:${APP_PORT}>|" /etc/apache2/sites-available/000-default.conf

# En Railway/producción no hay .env (las variables llegan como env vars reales);
# en local, con bind mount, .env está gitignored y no existe en un clone nuevo.
if [ ! -f "${ENV_FILE}" ] && [ -f "${APP_DIR}/.env.example" ]; then
  echo "No se encontró .env, copiando desde .env.example..."
  cp "${APP_DIR}/.env.example" "${ENV_FILE}"
fi

# docker-compose.local.yml y docker-compose.production.yml montan
# "./laravel-app:/var/www/sorherminia" como bind mount. Eso REEMPLAZA por completo
# el directorio de la imagen, incluyendo el vendor/ que "composer install" instaló
# en build time (Dockerfile). Como vendor/ está en .gitignore, un clone nuevo del
# repo no lo trae, así que en un primer "docker compose up" el contenedor arranca
# sin vendor/ y cualquier comando de artisan falla. Si detectamos que falta,
# lo instalamos aquí en vez de asumir que ya existe en el host.
if [ ! -f "${APP_DIR}/vendor/autoload.php" ]; then
  echo "vendor/ no encontrado (el bind mount tapa el vendor de la imagen). Instalando dependencias con Composer..."
  composer install --no-interaction --prefer-dist -d "${APP_DIR}"
fi

# Alinea el UID/GID de www-data con el del usuario del host (solo aplica con bind
# mount en local, y solo si HOST_UID/HOST_GID vienen definidos). Sin esto, todo lo
# que el contenedor escribe en storage/bootstrap/cache queda con dueño uid 33 en el
# host (en Linux esto es un dueño real, no solo dentro del contenedor), y el usuario
# del host se queda sin permiso para guardar/editar/eliminar esos archivos desde su
# propio editor o shell. En macOS con Docker Desktop (VirtioFS) esto normalmente no
# se nota porque el file sharing remapea el dueño de forma transparente, pero en
# Linux (p. ej. omarchy) sí bloquea al usuario.
if [ -n "${HOST_UID:-}" ] && [ -n "${HOST_GID:-}" ]; then
  echo "Alineando www-data a UID:GID ${HOST_UID}:${HOST_GID}..."
  groupmod -o -g "${HOST_GID}" www-data 2>/dev/null || true
  usermod -o -u "${HOST_UID}" www-data 2>/dev/null || true
fi

# IMPORTANTE: getenv() solo ve variables de entorno reales del contenedor (las que
# vienen de docker-compose "environment:" o de un ENV del Dockerfile). APP_KEY solo
# vive en .env, y .env lo carga Laravel (Dotenv) al arrancar, NO un "php -r" suelto.
# Antes este chequeo con getenv() siempre daba falso (nunca veía el valor real de
# .env), así que en CADA arranque se generaba una APP_KEY nueva y aleatoria, solo en
# memoria para ese proceso, y luego quedaba "horneada" en bootstrap/cache/config.php
# al correr config:cache más abajo. Resultado: la APP_KEY de .env quedaba muerta, y
# cada reinicio del contenedor invalidaba cookies/sesión firmadas con la key anterior.
# Ahora leemos la key real de .env y, si falta o es inválida, la generamos y
# persistimos con key:generate (que sí escribe en .env), en vez de solo exportarla
# para este proceso.
is_valid_app_key() {
  php -r '
    $key = getenv("APP_KEY") ?: "";
    if (!str_starts_with($key, "base64:")) {
      exit(1);
    }
    $raw = base64_decode(substr($key, 7), true);
    exit(($raw !== false && strlen($raw) === 32) ? 0 : 1);
  ' >/dev/null 2>&1
}

export APP_KEY="$(grep -E '^APP_KEY=' "${ENV_FILE}" 2>/dev/null | tail -n1 | cut -d '=' -f2-)"

if ! is_valid_app_key; then
  echo "APP_KEY ausente o inválida en .env. Generando y guardando una nueva..."
  php artisan key:generate --force || true
  export APP_KEY="$(grep -E '^APP_KEY=' "${ENV_FILE}" 2>/dev/null | tail -n1 | cut -d '=' -f2-)"
fi

if [ "${CACHE_STORE:-database}" = "database" ] && [ "${ALLOW_DATABASE_CACHE:-false}" != "true" ]; then
  echo "CACHE_STORE=database detected but cache tables may be missing. Falling back to CACHE_STORE=file."
  export CACHE_STORE="file"
fi

mkdir -p /var/www/sorherminia/storage/framework/views
mkdir -p /var/www/sorherminia/storage/framework/cache/data
mkdir -p /var/www/sorherminia/storage/framework/sessions
mkdir -p /var/www/sorherminia/storage/logs
mkdir -p /var/www/sorherminia/bootstrap/cache

chown -R www-data:www-data /var/www/sorherminia/storage /var/www/sorherminia/bootstrap/cache
chmod -R ug+rwX /var/www/sorherminia/storage /var/www/sorherminia/bootstrap/cache

export VIEW_COMPILED_PATH="/var/www/sorherminia/storage/framework/views"

echo "Preparing Laravel caches and storage link..."
php artisan optimize:clear || true
if [ ! -e "${APP_DIR}/public/storage" ]; then
  php artisan storage:link || true
fi
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Loaded Apache MPM modules:"
apache2ctl -M | grep mpm || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "Running migrations (with retries)..."
  for i in $(seq 1 15); do
    if php artisan migrate --force; then
      echo "Migrations completed."
      break
    fi
    echo "Migration attempt ${i} failed. Retrying in 5s..."
    sleep 5
  done
fi

# Los comandos artisan de arriba (cache, config, route, view, migrate/seed)
# corren como root y recrean subdirectorios de storage (ej.
# storage/framework/cache/data/xx/yy) como root. Re-asignamos la propiedad al
# final, justo antes de arrancar Apache, para que PHP (www-data) pueda escribir
# en storage y bootstrap/cache en runtime.
chown -R www-data:www-data /var/www/sorherminia/storage /var/www/sorherminia/bootstrap/cache
chmod -R ug+rwX /var/www/sorherminia/storage /var/www/sorherminia/bootstrap/cache

# Start Apache in the foreground
echo "Starting Apache..."
exec apache2-foreground
