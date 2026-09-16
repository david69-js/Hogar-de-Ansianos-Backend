# Hogar de Ancianos Sor Herminia — API

API en Laravel 12 del sistema de administración de medicamentos del Hogar de Ancianos Sor
Herminia. La interfaz de usuario es una aplicación aparte, hecha en Expo/React Native
([Hogar-de-Ansianos-Frontend](https://github.com/david69-js/Hogar-de-Ansianos-Frontend)),
que se publica como sitio web y como APK de Android.

Acá no hay pantallas: este repositorio es solo la API (JSON), los reportes en PDF, las
notificaciones push y las tareas programadas.

## Qué hace

- Residentes: ficha, fotos, documentos, signos vitales y condiciones médicas (CIE-10).
- Medicación: catálogo, prescripciones, horarios, registro de administración y de omisiones.
- Enfermería: asignación de una enfermera responsable por residente.
- Avisos: notificaciones push por medicamento pendiente, atrasado o sin registrar.
- Reportes en PDF: por residente, por enfermera, de incidencias, de cumplimiento y listado general.
- Auditoría de cambios, respaldos automáticos y recuperación de contraseña por código.

## Cómo está armado

| Pieza | Detalle |
|---|---|
| Framework | Laravel 12 (PHP 8.3) |
| Autenticación | Sanctum (tokens) + roles y permisos con spatie/laravel-permission |
| Base de datos | MySQL 8 |
| Archivos | Cloudflare R2 (compatible con S3) |
| Notificaciones | Firebase Cloud Messaging |
| Correo | Resend |
| PDF | dompdf |
| Contenedor | `Dockerfile` en la raíz: PHP 8.3 + Apache |
| Producción | Railway (servicios *Backend* y *Scheduler*), desde la rama `main` |

## Arrancar en local

```bash
git clone <este repo> && cd Hogar-de-Ansianos-Backend-Oficial

# En Linux (en macOS no hace falta): alinear el usuario del contenedor con el tuyo,
# si no, todo lo que se escriba en storage/ queda con dueño www-data y no lo podés editar.
export HOST_UID=$(id -u) HOST_GID=$(id -g)

docker compose -f docker-compose.local.yml up -d
```

El primer arranque, por sí solo, instala las dependencias de Composer si falta `vendor/`,
copia `.env.example` a `.env`, genera el `APP_KEY` y corre las migraciones.

| Servicio | Dirección |
|---|---|
| API | http://localhost:8000 |
| phpMyAdmin | http://localhost:8080 |
| Chequeo de salud | http://localhost:8000/up |

Comandos frecuentes:

```bash
docker exec -it laravel-sorherminia-app php artisan migrate
docker exec -it laravel-sorherminia-app php artisan db:seed
docker logs -f laravel-sorherminia-app
docker compose -f docker-compose.local.yml down
```

## Documentación

| Documento | De qué trata |
|---|---|
| [docs/API.md](docs/API.md) | Endpoints, permisos y ejemplos de cada petición |
| [docs/MANUAL-TECNICO.md](docs/MANUAL-TECNICO.md) | Instalación, scheduler, respaldos, monitoreo y variables de entorno |
| [docs/DESPLIEGUE-SERVIDOR-PROPIO.md](docs/DESPLIEGUE-SERVIDOR-PROPIO.md) | Cómo llevarlo a un servidor CentOS propio, como alternativa a Railway |
| [docs/BRANCHING.md](docs/BRANCHING.md) | Plan de ramas y despliegue de los dos repositorios |
| [docs/QA-2026-09-15.md](docs/QA-2026-09-15.md) | Revisión del código: seguridad, qué se usa y qué no |

## Reglas del proyecto

- **El `.env` nunca se versiona**, en ninguna de sus variantes. Las credenciales viven en
  el panel de Railway (o en el `.env` del servidor, si algún día se autoaloja).
- **No se crean migraciones de parche**: se editan las `hr_XX_create_*` y se rehace la base
  con `migrate:fresh --seed`. Los datos son de prueba; en producción hay que decidirlo a
  conciencia.
- Todo lo que ve el usuario va **en español**: mensajes de la API, validaciones, PDFs y
  detalles de auditoría.
