# Laravel Docker Environment

This project is configured to run fully in Docker for both local development and production.

## Prerequisites
- Docker
- Docker Compose

## Quick Start (Local Development)

1. **Start the containers**
   ```bash
   docker compose -f docker-compose.yml up -d
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
   docker compose -f docker-compose.yml down
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

mysql -h mysql_sorherminia -u root -p sorherminia
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

# Laravel + Docker + Railway

Este proyecto mantiene Docker para local/prod tradicional y ahora queda preparado para desplegarse en Railway con un solo servicio web.

## Requisitos
- Docker / Docker Compose (para local)
- Cuenta en Railway (para deploy)
- Repositorio en GitHub/GitLab/Bitbucket conectado a Railway

## Desarrollo local

1. Levantar contenedores:
```bash
docker-compose -f docker-compose.local.yml up -d
```

2. Entrar al contenedor app:
```bash
docker exec -it laravel-sorherminia-app bash
```

3. Ver logs:
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
## Deploy en Railway (paso a paso)

1. Sube estos cambios a tu repositorio remoto.

2. En Railway crea un proyecto nuevo:
- `New Project` -> `Deploy from GitHub repo`.
- Selecciona este repositorio.

3. Railway detectará el `Dockerfile` de la raíz y construirá la imagen automáticamente.

4. Agrega un servicio de base de datos MySQL en el mismo proyecto:
- `New` -> `Database` -> `MySQL`.

5. En el servicio web (tu app Laravel), configura estas variables en `Variables`:
- `APP_NAME=HogarDeAnsianos`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://TU_DOMINIO_PUBLICO` (puedes actualizarlo después)
- `LOG_CHANNEL=stderr`
- `LOG_LEVEL=info`
- `DB_CONNECTION=mysql`
- `DB_HOST=${{MySQL.MYSQL_HOST}}`
- `DB_PORT=${{MySQL.MYSQL_PORT}}`
- `DB_DATABASE=${{MySQL.MYSQL_DATABASE}}`
- `DB_USERNAME=${{MySQL.MYSQL_USER}}`
- `DB_PASSWORD=${{MySQL.MYSQL_PASSWORD}}`
- `SESSION_DRIVER=database`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=database`
- `RUN_MIGRATIONS=true`
- `APP_KEY=base64:...` (opcional, recomendado fijarlo manualmente)

6. Genera `APP_KEY` si quieres dejarlo fijo (recomendado):
- Localmente ejecuta:
```bash
cd laravel-app
php artisan key:generate --show
```
- Copia el valor en la variable `APP_KEY` de Railway.

7. En `Settings` del servicio web, verifica:
- `Healthcheck Path`: `/`
- `Restart Policy`: `On Failure` o `Always`

8. Despliega:
- Railway hace deploy automático al detectar push.
- Revisa logs del deploy; el contenedor ejecuta migraciones al iniciar.

9. Asigna dominio:
- `Settings` -> `Networking` -> `Generate Domain`.
- Copia ese dominio y actualiza `APP_URL` con el valor final.

## Notas importantes para Railway
- El contenedor ya usa el puerto dinámico `PORT` que Railway inyecta.
- Las migraciones se ejecutan al iniciar (`RUN_MIGRATIONS=true`).
- El storage local del contenedor es efímero; para archivos permanentes conviene migrar a S3/Cloudinary u otro almacenamiento externo.

## Solución de problemas rápida
- Si falla conexión BD: valida variables `DB_*` y que la referencia `${{MySQL.*}}` sea correcta al nombre real del servicio MySQL.
- Si ves error 500 por clave: define `APP_KEY` fijo en Railway.
- Si falla una migración en arranque: corrige la migración y vuelve a desplegar.
