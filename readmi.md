# Laravel Docker Environment

This project is configured to run fully in Docker for both local development and production.

## Prerequisites
- Docker
- Docker Compose

## Quick Start (Local Development)

1. **Start the containers**
   ```bash
   docker-compose -f docker-compose.local.yml up -d
   ```
   *The first time you run this, it will automatically:*
   - Unpack and install a new Laravel application if one doesn't exist
   - Install Composer dependencies
   - Copy `.env.example` to `.env` and set up database variables
   - Generate your `APP_KEY`
   - Wait for the database to be ready and run migrations

2. **Access the application**
   - Web App: http://localhost:8000
   - The application files are mapped to `./laravel-app` on your host machine.

3. **Stop the containers**
   ```bash
   docker-compose -f docker-compose.local.yml down
   ```

---

## Production Deployment

For production, the configuration includes SSL support via Nginx on port 443.

1. **Add SSL Certificates**
   Place your certificates in a `./ssl` directory in the root of the project:
   - `./ssl/server.crt`
   - `./ssl/server.key`

2. **Start the containers**
   ```bash
   docker-compose -f docker-compose.production.yml up -d
   ```

---

## Working with the Containers

### Entering the Containers

Often you will need to enter the container to run Artisan commands, Composer, or NPM.

**Enter the PHP/Laravel application container:**
```bash
docker exec -it laravel-sorherminia-app bash
```

**Enter the Database container:**
```bash
docker exec -it mysql_sorherminia bash
# Once inside, you can access mysql:
mysql -u sorherminia_user -p
# password: root
```

**Enter the Nginx webserver container:**
```bash
docker exec -it nginx_sorherminia sh
```

### Running Laravel Commands (Without entering the container)

You can run commands directly from your host machine by passing them to `docker exec`:

**Run an Artisan command:**
```bash
docker exec -it laravel-sorherminia-app php artisan migrate
docker exec -it laravel-sorherminia-app php artisan make:controller MyController
```

**Run a Composer command:**
```bash
docker exec -it laravel-sorherminia-app composer require <package-name>
docker exec -it laravel-sorherminia-app composer dump-autoload
```

### Viewing Logs

To see what is happening in the background:

**View all logs (and follow them live):**
```bash
docker-compose -f docker-compose.local.yml logs -f
```

**View logs for a specific service:**
```bash
docker logs -f laravel-sorherminia-app
docker logs -f mysql_sorherminia
docker logs -f nginx_sorherminia
```

## Structure Overview

- `Dockerfile`: The PHP 8.2 FPM image configuration with all required extensions (GD, Zip, MySQL, etc.)
- `docker-compose.local.yml`: Stack for local development (Port 8000).
- `docker-compose.production.yml`: Stack for production (Ports 80/443 with SSL).
- `nginx.conf`: Local web server configuration.
- `nginx-production.conf`: Production web server configuration with SSL termination.
- `setup-laravel.sh`: Automation script that handles permissions, dependencies, and database migrations.
- `entrypoint.sh`: Executed on container start; runs the setup script before starting PHP-FPM.
