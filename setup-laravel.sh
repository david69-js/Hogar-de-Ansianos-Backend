#!/bin/bash

# Change ownership of the working directory to www-data
chown -R www-data:www-data /var/www/sorherminia

# Function to check if Laravel project exists
check_laravel_exists() {
    if [ -f "/var/www/sorherminia/artisan" ] && [ -f "/var/www/sorherminia/composer.json" ] && [ -d "/var/www/sorherminia/app" ] && [ -d "/var/www/sorherminia/config" ]; then
        echo "Laravel project detected!"
        return 0
    else
        echo "No Laravel project found."
        return 1
    fi
}

# Function to create new Laravel project
create_laravel_project() {
    echo "Creating new Laravel project..."
    
    cd /var/www/sorherminia
    
    # Check if directory has files (except hidden files)
    if [ "$(ls -A . 2>/dev/null | grep -v '^\.')" ]; then
        echo "Directory has some files, but proceeding with Laravel installation..."
    fi
    
    # Create Laravel project as www-data user
    su -s /bin/bash www-data -c "composer create-project --prefer-dist laravel/laravel /tmp/laravel-temp"
    
    # Move all files from temp directory to current directory
    su -s /bin/bash www-data -c "
        cp -r /tmp/laravel-temp/* /var/www/sorherminia/ 2>/dev/null || true
        cp -r /tmp/laravel-temp/.[!.]* /var/www/sorherminia/ 2>/dev/null || true
        rm -rf /tmp/laravel-temp
    "
    
    echo "Laravel project created successfully!"
}

# Function to setup Laravel environment
setup_laravel_env() {
    echo "Setting up Laravel environment..."
    
    cd /var/www/sorherminia
    
    # Copy .env.example to .env if .env doesn't exist
    if [ ! -f ".env" ]; then
        su -s /bin/bash www-data -c "cp .env.example .env"
        echo ".env file created from .env.example"
    fi
    
    # Configure database settings in .env dynamically based on injected environment
    sed -i "s/DB_HOST=.*/DB_HOST=${DB_HOST:-mysql_sorherminia}/g" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE:-sorherminia}/g" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME:-sorherminia_user}/g" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD:-root}/g" .env
    
    echo "Database configuration updated in .env"
    
    # Install dependencies
    echo "Installing Composer dependencies..."
    su -s /bin/bash www-data -c "composer install --no-dev --optimize-autoloader"

    # Generate application key if not exists
    if ! grep -q "APP_KEY=base64:" .env; then
        echo "Generating application key..."
        su -s /bin/bash www-data -c "php artisan key:generate --no-interaction"
    fi

    # Create storage directories if they don't exist
    mkdir -p storage/logs
    mkdir -p storage/framework/cache
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/views
    mkdir -p bootstrap/cache
    
    # Set proper permissions
    chown -R www-data:www-data /var/www/sorherminia
    chmod -R 775 storage bootstrap/cache
    
    echo "Laravel environment setup completed!"
}

# Function to wait for database
wait_for_db() {
    echo "Waiting for database connection..."
    max_attempts=30
    attempt=1
    until su -s /bin/bash www-data -c "php -r 'new PDO(\"mysql:host=${DB_HOST:-mysql_sorherminia};dbname=${DB_DATABASE:-sorherminia};port=${DB_PORT:-3306}\", \"${DB_USERNAME:-sorherminia_user}\", \"${DB_PASSWORD:-root}\");' >/dev/null 2>&1"; do
        if [ $attempt -ge $max_attempts ]; then
            echo "Database still not ready after $max_attempts attempts"
            break
        fi
        echo "Attempt $attempt/$max_attempts: Database not ready yet..."
        sleep 2
        attempt=$((attempt+1))
    done
    echo "Database ready!"
}

# Function to run migrations
run_migrations() {
    echo "Running database migrations..."
    
    cd /var/www/sorherminia
    
    # Check if migrations table exists, if not create it
    if ! su -s /bin/bash www-data -c "php artisan migrate:status" >/dev/null 2>&1; then
        echo "Creating migrations table..."
        su -s /bin/bash www-data -c "php artisan migrate:install --no-interaction"
    fi
    
    # Run migrations
    su -s /bin/bash www-data -c "php artisan migrate --no-interaction --force"
    
    echo "Migrations completed!"
}

# Main execution
echo "Starting Laravel setup process..."

# Check if Laravel project already exists
if check_laravel_exists; then
    echo "Using existing Laravel project."
else
    create_laravel_project
fi

# Setup Laravel environment
setup_laravel_env

# Wait for database and run migrations
wait_for_db
run_migrations
