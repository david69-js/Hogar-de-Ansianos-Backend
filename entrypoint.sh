#!/bin/bash
set -e

# Run the setup script to initialize DB, run migrations, and install dependencies
/usr/local/bin/setup-laravel.sh

# Start PHP-FPM
echo "Starting PHP-FPM..."
exec php-fpm
