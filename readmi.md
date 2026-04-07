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
- `CACHE_STORE=database`
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
