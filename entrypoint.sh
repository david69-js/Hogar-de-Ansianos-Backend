#!/bin/bash
set -e

APP_PORT="${PORT:-8080}"

echo "Ensuring Apache uses a single MPM (prefork)..."
a2dismod mpm_event mpm_worker mpm_prefork >/dev/null 2>&1 || true
a2enmod mpm_prefork rewrite >/dev/null 2>&1 || true

echo "Configuring Apache to listen on port ${APP_PORT}..."
sed -i "s/Listen 80/Listen ${APP_PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${APP_PORT}>/g" /etc/apache2/sites-available/000-default.conf

if [ -z "${APP_KEY}" ]; then
  echo "APP_KEY is not set, generating a temporary runtime APP_KEY..."
  export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

echo "Preparing Laravel caches and storage link..."
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
