# Plan de ramas y despliegue — Hogar de Ancianos Sor Herminia

Propuesta, **todavía no aplicada**. Cubre los dos repositorios:

- `david69-js/Hogar-de-Ansianos-Backend` → API Laravel, desplegada en Railway.
- `david69-js/Hogar-de-Ansianos-Frontend` → app Expo, desplegada en Cloudflare Pages
  (`sorherminia.com`) y como APK de Android.

## 1. Situación actual

| | Backend | Frontend |
|---|---|---|
| Ramas | solo `main` | solo `main` |
| Despliegue | Railway, servicios *Sorherminia-Backend* y *Sorherminia-Scheduler*, entorno `production`, escuchando la rama **`main`**, construyendo con el `Dockerfile` de la raíz | Cloudflare Pages desde `main` |
| Consecuencia | cada push a `main` va directo a producción, sin lugar donde probar | igual |

No hay ambiente de pruebas: hoy la única forma de probar algo desplegado es publicarlo a
los usuarios. Ese es el problema que resuelve este plan.

## 2. Modelo propuesto: GitHub Flow con rama de integración

Git Flow clásico (con `develop`, `release/*` y `hotfix/*`) está pensado para software con
versiones numeradas y varias en mantenimiento a la vez. Acá hay un solo producto vivo y una
sola persona desarrollando, así que la variante conveniente es más simple: dos ramas
permanentes y ramas de trabajo de vida corta.

```
main    ─────●────────────●──────────────●─────────▶  producción (sorherminia.com / Railway)
              ╲          ╱ ╲            ╱
dev     ───●───●────●───●───●─────●────●──────────▶  pruebas (staging)
            ╲     ╱        ╲     ╱
feature/     ●───●           ●──●                     ramas de trabajo
```

### Ramas permanentes

| Rama | Qué es | Quién la mueve |
|---|---|---|
| `main` | Lo que está en producción **ahora**. Siempre desplegable | Solo se toca por *merge* desde `dev` (o `hotfix/*`) |
| `dev` | Integración: lo terminado que todavía no se publica | Recibe *merge* de las ramas de trabajo |

### Ramas de trabajo (se borran al integrarse)

| Prefijo | Para qué | Ejemplo |
|---|---|---|
| `feature/` | Funcionalidad nueva | `feature/reporte-incidencias` |
| `fix/` | Corrección de algo ya publicado, sin urgencia | `fix/fecha-prescripcion` |
| `hotfix/` | Urgencia en producción: sale de `main` y vuelve a `main` **y** a `dev` | `hotfix/login-500` |
| `chore/` | Mantenimiento sin efecto visible (dependencias, limpieza, documentación) | `chore/limpieza-plantilla-expo` |

## 3. Flujo de trabajo

**Algo nuevo:**

```bash
git switch dev && git pull
git switch -c feature/carrusel-fotos
# ... trabajo, commits ...
git push -u origin feature/carrusel-fotos
# PR:  feature/carrusel-fotos  →  dev
```

**Publicar a producción** (cuando `dev` está probado en staging):

```bash
# PR:  dev  →  main   (título: "Release 2026-09-20")
# al hacer merge, Railway y Cloudflare despliegan producción solos
git switch dev && git merge main   # mantener dev al día después del release
```

**Urgencia en producción:**

```bash
git switch main && git pull
git switch -c hotfix/login-500
# ... arreglo ...
# PR:  hotfix/login-500 → main   (publicar)
# PR:  hotfix/login-500 → dev    (para no perder el arreglo en el próximo release)
```

Regla de oro: **nunca commitear directo sobre `main`**, ni siquiera "un cambio chiquito".

## 4. Qué hay que configurar

### 4.1 GitHub (los dos repos)

1. Crear la rama: `git switch -c dev && git push -u origin dev`.
2. Settings → Branches → *Add rule* para `main`:
   - Require a pull request before merging.
   - *Do not allow bypassing the above settings* apagado (sos el único que integra; si lo
     dejás prendido no vas a poder hacer merge de tus propios PRs sin otro revisor).
   - Require status checks (cuando haya CI).
3. Settings → General → *Default branch*: dejar `main`, pero poner `dev` como base
   sugerida de los PRs (Settings → Pull Requests) para no abrir PRs contra `main` por error.
4. **Ambos repos en privado.** El backend estuvo público y por eso se filtraron
   credenciales (ver `docs/QA-2026-09-15.md`, §1.1).

### 4.2 Railway (backend)

Railway separa por *environments*, no por ramas, así que:

1. Duplicar el entorno: *Environments* → **New Environment** → `staging`, copiando las
   variables de `production`.
2. En `staging`, para los servicios *Sorherminia-Backend* y *Sorherminia-Scheduler*:
   Settings → Source → **Branch: `dev`**.
3. Darle a `staging` **su propia base de datos** (agregar un servicio MySQL en ese entorno).
   No debe apuntar nunca a la base de producción.
4. Variables distintas por entorno:
   - `APP_ENV=staging`, `APP_DEBUG=true` en staging; `production` / `false` en producción.
   - `BACKUP_NAME=backups-staging` para que los respaldos no se mezclen en R2.
   - Idealmente un *bucket* o prefijo distinto de R2 para staging.
5. Dejar `production` escuchando `main`, como está hoy.

### 4.3 Cloudflare Pages (frontend)

1. Settings → Builds & deployments → *Production branch*: `main`.
2. *Preview deployments*: **All branches** (o solo `dev`). Cada push a `dev` genera una URL
   de vista previa; la de `dev` es estable: `dev.<proyecto>.pages.dev`.
3. Variables de entorno **por ambiente** (Pages las separa en *Production* y *Preview*):
   - Production → `EXPO_PUBLIC_API_URL = https://<backend de producción>/api`
   - Preview → `EXPO_PUBLIC_API_URL = https://<backend de staging>/api`
   - Las `EXPO_PUBLIC_FIREBASE_*` pueden ser las mismas en ambos.
4. El dominio `sorherminia.com` se queda apuntando solo a producción.

### 4.4 Variables de entorno: regla única

- **Ningún `.env` entra al repositorio, nunca.** Ambos `.gitignore` ya lo impiden
  (`.env*` con excepción de `.env.example`); mantener esa regla.
- La fuente de verdad son los paneles: Railway (backend) y Cloudflare Pages (frontend).
- `laravel-app/.env.example` es la lista oficial de claves necesarias: cuando se agrega una
  variable nueva, se agrega ahí **vacía**, en el mismo PR.
- Al crear un ambiente nuevo se generan credenciales propias; no se copian las de
  producción.

## 5. Convenciones

**Nombres de rama**: `tipo/descripcion-corta-en-minusculas-con-guiones`.

**Commits**: Conventional Commits, que es lo que ya venís usando.

```
feat: asignar enfermera responsable a cada residente
fix: corregir el orden del historial de navegación
chore: quitar dependencias sobrantes de la plantilla de Expo
docs: actualizar el manual técnico
```

**Título del PR de release**: `Release AAAA-MM-DD`, con la lista de cambios en el cuerpo.
Sirve de bitácora para la tesis.

## 6. Checklist antes de publicar (`dev` → `main`)

- [ ] Probado en staging: entrar, registrar una dosis, generar un PDF, subir una foto.
- [ ] `npx tsc --noEmit` y `npx eslint` sin errores nuevos (frontend).
- [ ] Migraciones nuevas ejecutadas en staging **y** listas para producción.
      Recordar: en este proyecto no se crean migraciones de parche; se editan las
      `hr_XX_create_*` y se corre `migrate:fresh --seed`. Eso **borra los datos**, así que
      en producción hay que decidir a conciencia entre `migrate:fresh` o un `ALTER` manual.
- [ ] Permisos nuevos sembrados: `php artisan db:seed --class=RolesAndPermissionsSeeder --force`.
- [ ] Variables de entorno nuevas cargadas en Railway/Cloudflare **antes** del merge.
- [ ] Respaldo de la base de producción del día (el programado corre 02:15).

## 7. Orden sugerido para arrancar

1. Poner los dos repos en privado y rotar las credenciales filtradas.
2. Crear `dev` en ambos repos a partir de `main`.
3. Crear el entorno `staging` en Railway con su propia base.
4. Configurar las variables de Preview en Cloudflare Pages.
5. Proteger `main` en GitHub.
6. A partir de ahí, todo cambio nace de `dev`.
