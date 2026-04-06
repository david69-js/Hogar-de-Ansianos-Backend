#!/bin/bash
set -e

# Run the setup script to initialize DB, run migrations, and install dependencies
/usr/local/bin/setup-laravel.sh

# Start Apache
echo "Starting Apache..."
exec apache2-foreground
