# Manual técnico — Instalación, configuración y respaldo

Este documento complementa `readmi.md` (instalación local básica). Se enfoca en lo que `readmi.md` no cubre: cómo queda corriendo el *scheduler* en el despliegue real (Railway), cómo funciona el respaldo de la base de datos, y cómo restaurarlo si hace falta.

## 1. Componentes del sistema

| Componente | Dónde vive | Quién lo ejecuta |
|---|---|---|
| API Laravel (web) | Un contenedor construido desde `Dockerfile` (raíz del repo) | Railway, servicio principal |
| Scheduler (avisos push + backups) | El **mismo** `Dockerfile`, como un **segundo servicio** de Railway | Railway, servicio `scheduler` (ver sección 3) |
| Base de datos | MySQL gestionado por Railway (o `mysql:8.0` en Docker Compose local) | — |
| Archivos (fotos, documentos, respaldos) | Cloudflare R2 (bucket `sorherminia`, disco `r2` en `config/filesystems.php`) | — |

`docker-compose.production.yml` (nginx + app + scheduler + mysql, todo en un servidor propio) **no es el despliegue real hoy** — es una alternativa ya preparada por si algún día se migra de Railway a un VPS propio. Si se usa esa vía, el servicio `scheduler` ya viene incluido en ese archivo y no requiere nada de la sección 3.

## 2. Instalación local

Ver `readmi.md`, sección "Quick Start (Local Development)". En resumen:

```bash
docker compose -f docker-compose.local.yml up -d
```

Esto levanta `app`, `scheduler`, `webserver` (nginx, puerto 8000), `db` (MySQL) y `phpmyadmin` (puerto 8080). El primer arranque instala dependencias, genera `APP_KEY` y corre migraciones automáticamente (ver `entrypoint.sh`).

**Gotcha conocido**: si reconstruyes la imagen (`docker compose build app`) y recreas el contenedor `app` con `--no-deps`, su IP interna cambia mientras el `webserver` (nginx) sigue con la conexión vieja en caché — la API responde `502 Bad Gateway`. Arreglo: `docker restart nginx_sorherminia`, o simplemente levantar todo el stack junto (`docker compose up -d`) en vez de servicios sueltos.

## 3. Scheduler en Railway (avisos push + backups)

Laravel no tiene cron propio: algo tiene que estar corriendo `schedule:work` (o `schedule:run` cada minuto) todo el tiempo para que corran:
- `app:check-pending-medications` (avisos push, cada minuto)
- `app:check-medication-stock` (alertas de inventario, diario 07:00)
- `backup:clean` / `backup:run --only-db` / `backup:monitor` (respaldo diario, sección 4)

El `Dockerfile` de este proyecto define un único `ENTRYPOINT` (`entrypoint.sh`) que siempre termina arrancando Apache — un "Custom Start Command" en Railway **no basta** para cambiar eso, porque `entrypoint.sh` no reenvía argumentos a nada. Por eso el propio `entrypoint.sh` ahora revisa la variable `PROCESS_TYPE`:

```bash
if [ "${PROCESS_TYPE:-web}" = "scheduler" ]; then
  exec php artisan schedule:work
fi
```

### Cómo crear el segundo servicio en Railway

1. En el mismo proyecto de Railway, **+ New → GitHub Repo** y selecciona este mismo repositorio otra vez (o **+ New → Empty Service** y luego conéctalo al mismo repo/rama).
2. En **Variables** de ese nuevo servicio, copia las mismas variables que ya tiene el servicio web (`DB_*`, `AWS_*`/R2, `MAIL_*`, `APP_KEY`, `FIREBASE_*`, etc.) y agrega:
   - `PROCESS_TYPE=scheduler`
   - `RUN_MIGRATIONS=false` — **importante**: si ambos servicios corrieran migraciones a la vez en cada deploy, hay riesgo de que compitan sobre el mismo esquema. Solo el servicio web debe migrar.
3. En **Settings** de ese servicio, quita el dominio público / healthcheck HTTP si Railway lo pide por defecto (este servicio no sirve tráfico web, solo corre el scheduler en segundo plano).
4. Despliega. En los logs deberías ver `PROCESS_TYPE=scheduler: corriendo el scheduler de Laravel en vez de Apache...` seguido de silencio (normal: `schedule:work` no imprime nada hasta que un comando programado corre).

### Cómo confirmar que está funcionando

```bash
# Desde cualquier entorno con acceso al proyecto (local o el propio servicio):
php artisan schedule:list
```

Debe listar `app:check-pending-medications`, `app:check-medication-stock`, `backup:clean`, `backup:run --only-db`, `backup:monitor` (ver `routes/console.php`). Si el servicio scheduler de Railway está corriendo, las notificaciones push y las alertas de inventario deben empezar a llegar sin que nadie ejecute nada manualmente.

## 4. Respaldo de la base de datos

Implementado con `spatie/laravel-backup`. Solo respalda la **base de datos** (no el código, que vive en git, ni los archivos de residentes, que ya viven en R2 con su propia redundancia).

| Aspecto | Valor | Dónde se configura |
|---|---|---|
| Qué se respalda | Solo BD (`--only-db`) | `routes/console.php` |
| Dónde queda | Cloudflare R2, mismo bucket que las fotos/documentos, carpeta `backups/` | `config/backup.php` → `disks` |
| Frecuencia | Diario, 02:15 (hora del servidor) | `routes/console.php` |
| Retención | Todos los respaldos de los últimos 14 días; después, uno por mes durante 6 meses más | `config/backup.php` → `cleanup.default_strategy` |
| Aviso por correo | Sí — backup exitoso, backup fallido, backup viejo/pesado (`backup:monitor`) | `config/backup.php` → `notifications.mail.to` |

**Variable de entorno que falta configurar en producción**: `BACKUP_NOTIFICATION_EMAIL` — el correo real que debe recibir los avisos. Si no se define, cae de vuelta a `MAIL_FROM_ADDRESS`, que normalmente es un remitente genérico, no una bandeja que alguien revise.

### Generar un respaldo manual

```bash
php artisan backup:run --only-db
```

### Ver qué respaldos existen

```bash
php artisan backup:list
```

Muestra si el disco es alcanzable, si el respaldo más reciente está "sano" (no muy viejo, no muy pesado) y cuánto espacio se está usando.

### Restaurar un respaldo — paso a paso

1. Bajar el archivo desde R2 (vía el cliente que prefieras — `rclone`, el dashboard de Cloudflare, o el SDK de S3 apuntando al mismo `AWS_ENDPOINT`/`AWS_BUCKET` del `.env`). El archivo está en `backups/AAAA-MM-DD-HH-mm-ss.zip`.
2. Descomprimirlo — adentro hay un único archivo `db-dumps/mysql-sorherminia.sql` (o similar, según `database_dump_filename_base`).
3. **Antes de restaurar sobre una base de datos con datos reales**, respaldar el estado actual primero (`php artisan backup:run --only-db`), por si hace falta revertir.
4. Restaurar el dump:
   ```bash
   # Desde dentro del contenedor/servicio con acceso a la BD:
   mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < ruta/al/dump.sql
   ```
5. Verificar que la aplicación responde normal (`GET /api/me` con un token válido, revisar que `residents`/`users` tengan los conteos esperados).

No existe (todavía) un comando de un solo paso "restaurar el último backup" — es un procedimiento manual deliberado, para no correr el riesgo de que un comando automatizado sobrescriba datos reales por error.

## 5. Variables de entorno relevantes (referencia rápida)

No se listan valores reales aquí — solo qué existe y para qué sirve cada grupo.

| Grupo | Variables | Para qué |
|---|---|---|
| App | `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | Config base de Laravel |
| Base de datos | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Conexión MySQL |
| Cloudflare R2 | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | Fotos/documentos de residentes y ahora también respaldos de BD |
| Correo | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Notificaciones de backup |
| Backup | `BACKUP_NOTIFICATION_EMAIL`, `BACKUP_ARCHIVE_PASSWORD` (opcional) | A quién avisar; si se define `BACKUP_ARCHIVE_PASSWORD`, los zips quedan cifrados (no configurado por defecto) |
| Firebase | credenciales del servicio (ver `config/services.php`) | Notificaciones push (FCM) |
| Scheduler (solo el segundo servicio de Railway) | `PROCESS_TYPE=scheduler`, `RUN_MIGRATIONS=false` | Ver sección 3 |

## 6. Limitaciones conocidas (a propósito, no pendientes)

- Los respaldos no incluyen archivos de R2 (fotos/documentos) — se apoya en la propia redundancia de Cloudflare para esos.
- Los zips de respaldo no van cifrados por defecto (el bucket R2 ya es privado). Si se requiere una capa adicional, definir `BACKUP_ARCHIVE_PASSWORD`.
- La restauración es manual a propósito — no hay un comando de un clic que sobrescriba la base de datos en producción.
