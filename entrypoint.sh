#!/bin/bash
set -e

APP_PORT="${PORT:-8080}"

echo "Ensuring Apache uses a single MPM (prefork)..."
a2dismod mpm_event mpm_worker mpm_prefork >/dev/null 2>&1 || true
a2enmod mpm_prefork rewrite >/dev/null 2>&1 || true

echo "Configuring Apache to listen on port ${APP_PORT}..."
sed -i "s/Listen 80/Listen ${APP_PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${APP_PORT}>/g" /etc/apache2/sites-available/000-default.conf

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

if ! is_valid_app_key; then
  echo "APP_KEY is missing/invalid. Generating a temporary runtime APP_KEY..."
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
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
php artisan storage:link || true
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

# Start Apache in the foreground
echo "Starting Apache..."
exec apache2-foreground
