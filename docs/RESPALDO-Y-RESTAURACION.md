# Respaldo y restauración de la base de datos

Cómo se generan los respaldos, cómo restaurarlos y —sobre todo— qué revisar
después, porque restaurar el dump **no** deja la base lista del todo.

Este documento cubre el procedimiento completo. La sección 4 del
[MANUAL-TECNICO.md](MANUAL-TECNICO.md) describe la configuración; acá está la
operación.

---

## 1. Qué se respalda y dónde queda

| | |
|---|---|
| Qué | Solo la **base de datos**. Ni el código (vive en git) ni las fotos y documentos (viven en R2 con su propia redundancia) |
| Cómo | `spatie/laravel-backup`, comando `backup:run --only-db` |
| Cuándo | Todos los días a las 02:15, desde el servicio *Scheduler* de Railway |
| Dónde | Cloudflare R2, bucket `sorherminia`, carpeta `backups/` |
| Formato | `backups/AAAA-MM-DD-HH-mm-ss.zip`, con un único `db-dumps/mysql-*.sql` adentro |
| Retención | Todos los de los últimos 14 días; después, uno por mes durante 6 meses |

El respaldo lo corre el servicio **Scheduler**, no el backend. Si ese servicio
está caído, no hay respaldos y nadie avisa hasta que `backup:monitor` (03:00)
manda el correo de "respaldo viejo".

## 2. Generar un respaldo a mano

Antes de cualquier operación riesgosa sobre producción — y **siempre** antes de
restaurar sobre datos que importan:

```bash
php artisan backup:run --only-db
```

Ver qué hay guardado y si el más reciente está sano:

```bash
php artisan backup:list
```

## 3. Restaurar — procedimiento

MySQL en Railway **no está expuesta a internet** (no tiene proxy TCP), así que
todo se hace desde adentro del contenedor del backend. Eso no es un obstáculo:
la imagen ya trae `mysql`, `unzip`, `zip` y `curl`, y las credenciales de la
base y de R2 ya están como variables de entorno.

### 3.1 Entrar al contenedor

```bash
railway ssh --project <PROJECT_ID> --environment production --service Sorherminia-Backend
```

### 3.2 Ver qué respaldos existen en R2

Las credenciales de R2 ya están cargadas, así que se puede listar con el propio
disco de Laravel:

```bash
php artisan tinker --execute="print_r(Storage::disk('r2')->allFiles('backups'));"
```

### 3.3 Bajar y descomprimir

```bash
php artisan tinker --execute="file_put_contents('/tmp/backup.zip', Storage::disk('r2')->get('backups/AAAA-MM-DD-HH-mm-ss.zip'));"
cd /tmp && unzip -o backup.zip && ls db-dumps/
```

Si el archivo no es un respaldo automático sino un `.sql` subido a mano, basta
con bajarlo: `curl -o /tmp/dump.sql "<URL firmada>"`.

### 3.4 Importar

```bash
MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" < db-dumps/mysql-*.sql
```

Se usa `MYSQL_PWD` en vez de `-p"$DB_PASSWORD"` para que la contraseña no quede
escrita en la línea de comandos (visible en `ps` y en el historial del shell).

**`mysql` no dice nada cuando funciona.** Si no imprime nada, el import salió
bien; cualquier salida es un error.

## 4. Lo que hay que revisar después — la parte que se olvida

> Restaurar el dump **no** deja la base al día con el código. Esta es la causa
> más probable de que algo falle después de una restauración aparentemente
> exitosa.

El dump incluye la tabla `migrations`. Al restaurarla, Laravel lee ahí que todas
las migraciones ya se ejecutaron, así que el `php artisan migrate --force` que
corre en cada arranque (`entrypoint.sh`) **no vuelve a ejecutar ninguna**.

Eso choca de frente con la regla del proyecto de **editar las migraciones
originales en vez de crear migraciones de parche** (ver el `README.md`): el
archivo `hr_XX_create_*.php` conserva su nombre cuando se le agrega una columna,
así que para Laravel "ya corrió" — y la columna nueva nunca se crea.

**Resultado:** si el respaldo es anterior a un cambio de esquema, la base queda
con el esquema viejo y el código nuevo. Leer suele seguir funcionando; escribir
falla con `Unknown column`.

### 4.1 Comprobar qué columnas faltan

```bash
MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" -e "
SELECT 'medications.image' AS columna, COUNT(*) AS existe FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='medications' AND column_name='image'
UNION ALL SELECT 'residents.admission_date', COUNT(*) FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='residents' AND column_name='admission_date'
UNION ALL SELECT 'residents.assigned_nurse_id', COUNT(*) FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='residents' AND column_name='assigned_nurse_id'
UNION ALL SELECT 'medication_logs.incident_type', COUNT(*) FROM information_schema.columns
  WHERE table_schema=DATABASE() AND table_name='medication_logs' AND column_name='incident_type';"
```

Todo lo que dé `0` hay que agregarlo a mano.

**Al agregar una columna nueva al proyecto, agregala también a esa consulta.**
Es la única lista de "cosas que un respaldo viejo no trae".

### 4.2 Agregar a mano lo que falte

```bash
MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" \
  -e "ALTER TABLE medications ADD COLUMN image VARCHAR(255) NULL AFTER concentration;"
```

Sí, es un `ALTER` a mano, que es justo lo que la regla del proyecto evita. Pero
acá no hay alternativa: esa regla asume que la base se puede rehacer con
`migrate:fresh`, y restaurar un respaldo es precisamente lo contrario. La
definición de la columna tiene que copiarse de la migración `hr_XX_create_*`
correspondiente, para que el tipo coincida exactamente.

Lo que **no** sirve: borrar la fila de `migrations` y correr `migrate` de nuevo.
La migración es un `CREATE TABLE`, no un `ALTER`, así que fallaría porque la
tabla ya existe.

## 5. Verificar que quedó bien

```bash
# 1. La API responde y se puede iniciar sesión
curl -s -o /dev/null -w "%{http_code}\n" https://TU_DOMINIO/up

# 2. Los conteos son los esperados (con un token válido)
for ep in users residents medications prescriptions medication-schedules; do
  echo -n "$ep: "
  curl -s "https://TU_DOMINIO/api/$ep" -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json" | python3 -c "import sys,json;print(len(json.load(sys.stdin)))"
done
```

3. Correr de nuevo la consulta de la sección 4.1: todo debe dar `1`.
4. Probar una **escritura** de lo que se restauró (editar un medicamento, marcar
   una dosis). Leer funciona aunque falte una columna; escribir no.

## 6. Errores que se pagan caro

- **Restaurar sin respaldar antes.** El import reemplaza las tablas. Si el
  archivo resulta ser el equivocado, sin un respaldo previo no hay vuelta atrás.
- **Dar por buena la restauración porque la app carga.** Leer funciona con el
  esquema viejo; el error aparece recién cuando alguien intenta guardar algo.
  Siempre hacer el paso 4 y la prueba de escritura del paso 5.
- **Poner la contraseña con `-p` en la línea de comandos.** Queda en `ps` y en el
  historial. Usar `MYSQL_PWD`.
- **Exponer MySQL con un proxy TCP para importar desde afuera.** No hace falta:
  el contenedor ya tiene el cliente y llega por la red interna. Un proxy abierto
  es una base de datos de salud publicada en internet.
- **Correr `migrate:fresh` en producción para "arreglar" el esquema.** Borra
  todo. Es lo que obliga a restaurar en primer lugar.
