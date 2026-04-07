#!/bin/bash
set -e

# Run standard Laravel caching to optimize performance in production
echo "Caching configuration and routes..."
php artisan config:cache || echo "Failed to cache config"
php artisan route:cache || echo "Failed to cache routes"
php artisan view:cache || echo "Failed to cache views"

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force || echo "Migrations failed. Database might not be ready or reachable yet."

# Start Apache in the foreground
echo "Starting Apache..."
exec apache2-foreground
