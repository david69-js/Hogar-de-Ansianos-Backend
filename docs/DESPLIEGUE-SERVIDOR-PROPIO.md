# Despliegue en servidor propio (CentOS / Rocky / AlmaLinux)

Alternativa a Railway, por si hay que mover el sistema a un servidor convencional.
Todo corre con Docker Compose, con la misma imagen que se usa hoy en Railway, así que
no hay dos versiones del código que mantener.

Archivos que intervienen (viven en la raíz del repo):

| Archivo | Papel |
|---|---|
| `Dockerfile` | La aplicación: PHP 8.3 + Apache. El mismo que usa Railway |
| `entrypoint.sh` | Arranque: caches, migraciones, credenciales de Firebase, Apache o scheduler |
| `docker-compose.production.yml` | Los cuatro contenedores: app, scheduler, nginx y MySQL |
| `nginx-production.conf` | Termina TLS y reenvía a la aplicación |
| `php-uploads.ini` | Límites de subida (12 MB por archivo) |

Diferencia con Railway: allá la aplicación se expone directo por su propio Apache y la
plataforma pone el HTTPS. Acá, nginx hace de puerta de entrada, resuelve el certificado y
reenvía a la aplicación.

---

## 1. Lo que hace falta antes de empezar

- Un servidor CentOS Stream 9 / Rocky 9 / AlmaLinux 9 con al menos 2 GB de RAM.
- Acceso `sudo`.
- Un dominio apuntando (registro A) a la IP del servidor.
- Los puertos 80 y 443 abiertos desde internet.
- Las credenciales que hoy están en Railway: base de datos, R2, Firebase y correo.

## 2. Instalar Docker

```bash
sudo dnf -y install dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"   # cerrar sesión y volver a entrar
docker compose version            # confirmar que responde
```

## 3. Cortafuegos y SELinux

CentOS trae los dos activos, y son la causa habitual de que "todo levanta pero no
responde nada".

```bash
# Cortafuegos: abrir solo web. El 3306 NO se abre: la base solo se usa desde adentro.
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload

# SELinux: permitir que los contenedores lean los archivos montados del repo.
sudo setsebool -P container_manage_cgroup on
```

Si al levantar aparece "Permission denied" en un archivo montado, agregar `:z` al final
de ese montaje en el compose (por ejemplo `./ssl:/etc/nginx/ssl:ro,z`). Es la forma en que
SELinux marca el archivo como compartible con el contenedor.

## 4. Traer el proyecto y configurarlo

```bash
sudo mkdir -p /opt/sorherminia && sudo chown "$USER" /opt/sorherminia
git clone https://github.com/david69-js/Hogar-de-Ansianos-Backend.git /opt/sorherminia
cd /opt/sorherminia

cp laravel-app/.env.example laravel-app/.env
```

Editar `laravel-app/.env` con los valores reales. Lo mínimo:

```ini
APP_NAME="Hogar Sor Herminia"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # se genera en el paso 6
APP_URL=https://api.tudominio.com

DB_CONNECTION=mysql
DB_HOST=mysql_sorherminia
DB_DATABASE=sorherminia
DB_USERNAME=sorherminia_user
DB_PASSWORD=                  # inventar una larga
MYSQL_DATABASE=sorherminia
MYSQL_USER=sorherminia_user
MYSQL_PASSWORD=               # la misma de DB_PASSWORD
MYSQL_ROOT_PASSWORD=          # otra distinta, también larga

CACHE_STORE=database
ALLOW_DATABASE_CACHE=true
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# Almacenamiento de fotos y documentos: lo más simple es seguir usando Cloudflare R2.
FILESYSTEM_DISK=r2
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_BUCKET=
AWS_ENDPOINT=
AWS_DEFAULT_REGION=auto
AWS_USE_PATH_STYLE_ENDPOINT=true

# Correo (recuperación de contraseña)
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=
RESEND_API_KEY=

# Notificaciones push
FIREBASE_CREDENTIALS=/var/www/sorherminia/storage/app/firebase-service-account.json
FIREBASE_PROJECT_ID=
FIREBASE_PRIVATE_KEY_ID=
FIREBASE_PRIVATE_KEY=
FIREBASE_CLIENT_EMAIL=
FIREBASE_CLIENT_ID=
FIREBASE_CLIENT_CERT_URL=

# Quién puede llamar a la API desde el navegador
CORS_ALLOWED_ORIGINS=https://sorherminia.com
```

`FIREBASE_CREDENTIALS` es una **ruta**: `entrypoint.sh` escribe ese archivo en cada
arranque con lo que haya en las `FIREBASE_*`. No hay que subir ninguna llave al repo.

**El `.env` no se versiona nunca.** Vive solo en el servidor, con permisos cerrados:

```bash
chmod 600 laravel-app/.env
```

## 5. Certificado TLS

**Opción A — Let's Encrypt (lo normal).** Primero un certificado temporal para que nginx
pueda arrancar, después el real:

```bash
mkdir -p ssl certbot
openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
  -keyout ssl/server.key -out ssl/server.crt -subj "/CN=temporal"

docker compose -f docker-compose.production.yml up -d webserver

sudo dnf -y install certbot
sudo certbot certonly --webroot -w ./certbot -d api.tudominio.com

# Apuntar nginx al certificado real
sudo cp /etc/letsencrypt/live/api.tudominio.com/fullchain.pem ssl/server.crt
sudo cp /etc/letsencrypt/live/api.tudominio.com/privkey.pem  ssl/server.key
docker compose -f docker-compose.production.yml restart webserver
```

Renovación automática (el certificado dura 90 días):

```bash
sudo tee /etc/cron.d/certbot-sorherminia >/dev/null <<'EOF'
0 3 * * 1 root certbot renew --webroot -w /opt/sorherminia/certbot --quiet && \
  cp /etc/letsencrypt/live/api.tudominio.com/fullchain.pem /opt/sorherminia/ssl/server.crt && \
  cp /etc/letsencrypt/live/api.tudominio.com/privkey.pem /opt/sorherminia/ssl/server.key && \
  docker restart nginx_sorherminia
EOF
```

**Opción B — autofirmado**, solo para probar en una red interna. El navegador va a avisar
que el sitio no es de fiar; la APK de Android directamente rechaza la conexión.

## 6. Levantar todo

```bash
cd /opt/sorherminia
docker compose -f docker-compose.production.yml up -d --build

# Generar la llave de la aplicación (una sola vez)
docker compose -f docker-compose.production.yml exec app php artisan key:generate --force

# Crear las tablas y los datos base (roles, permisos y la cuenta de administración)
docker compose -f docker-compose.production.yml exec app php artisan migrate --force
docker compose -f docker-compose.production.yml exec app php artisan db:seed --force
```

`migrate --force` ya lo corre `entrypoint.sh` en cada arranque; el comando de arriba es por
si se quiere hacer a mano la primera vez.

## 7. Comprobar que quedó bien

```bash
# 1. La aplicación responde
curl -k https://api.tudominio.com/up                 # health de Laravel
curl -k https://api.tudominio.com/                   # {"service":"...","status":"ok"}

# 2. Se puede iniciar sesión
curl -k -X POST https://api.tudominio.com/api/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admin@hogar.com","password":"..."}'

# 3. El límite de intentos funciona (el 6.º debe dar 429)
for i in $(seq 1 6); do curl -k -s -o /dev/null -w "%{http_code} " \
  -X POST https://api.tudominio.com/api/login -H "Accept: application/json" \
  -H "Content-Type: application/json" -d '{"email":"admin@hogar.com","password":"mala"}'; done

# 4. El scheduler está corriendo
docker logs laravel-sorherminia-scheduler --tail 20

# 5. Se pueden subir fotos de hasta 12 MB (413 = nginx o PHP están cortando)
```

Después, apuntar el frontend: cambiar `EXPO_PUBLIC_API_URL` en Cloudflare Pages a
`https://api.tudominio.com/api` y volver a desplegar; para la APK, reconstruirla con esa
misma variable.

## 8. Operación diaria

```bash
# Registros
docker compose -f docker-compose.production.yml logs -f app

# Actualizar a la última versión del código
cd /opt/sorherminia && git pull
docker compose -f docker-compose.production.yml up -d --build

# Respaldo manual de la base
docker compose -f docker-compose.production.yml exec app php artisan backup:run --only-db

# Arrancar solo tras reiniciar el servidor: ya queda automático porque Docker está
# habilitado con systemctl enable y los contenedores usan restart: unless-stopped.
```

Los respaldos programados (02:15 todos los días) los ejecuta el contenedor `scheduler` y
se guardan en el disco configurado en `config/backup.php` — con `FILESYSTEM_DISK=r2` van a
Cloudflare R2, igual que en Railway. Si se prefiere guardarlos en el propio servidor, hay
que cambiar ese disco y montar un volumen para ellos.

## 9. Diferencias con Railway, para tener presente

| Tema | Railway | Servidor propio |
|---|---|---|
| HTTPS | Lo pone la plataforma | Certificado propio, con renovación programada |
| Variables | Panel de Railway | `laravel-app/.env` en el servidor |
| Scheduler | Un segundo servicio con `PROCESS_TYPE=scheduler` | El contenedor `scheduler` del compose |
| Base de datos | Servicio administrado, con respaldos de la plataforma | MySQL en un contenedor: **los respaldos son responsabilidad tuya** |
| Actualizar | `git push` a `main` | `git pull` + `up -d --build` en el servidor |
| Registros | Panel | `docker logs` (conviene configurar rotación) |

## 10. Errores que se pagan caro

- **Abrir el 3306 al mundo.** El compose lo deja en `127.0.0.1:3306` a propósito. Para
  conectarse desde afuera, túnel SSH: `ssh -L 3306:127.0.0.1:3306 usuario@servidor`.
- **Dejar `APP_DEBUG=true`.** Muestra variables de entorno y rutas internas en cada error.
- **Subir el `.env` al repositorio.** Ya pasó una vez; el `.gitignore` ahora lo impide.
- **Olvidar los respaldos.** En Railway la plataforma ayuda; en un servidor propio, si no
  corre el scheduler, no hay respaldos y nadie avisa.
