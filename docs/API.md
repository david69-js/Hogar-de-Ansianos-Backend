# Documentación de la API - Hogar de Ancianos Sor Herminia

Estructura de cada petición (payloads JSON) contra la API, tomada de `routes/api.php`.

> Revisado el 15/09/2026. Si algo no coincide, `php artisan route:list --path=api` manda.

## Autenticación (rutas públicas)

### 1. Inicio de sesión (`POST /api/login`)
Devuelve el token de acceso (`access_token`), el usuario, sus roles y sus permisos.
```json
{
  "email": "laura@ejemplo.com",
  "password": "password123"
}
```

**Límite de intentos**: 5 por minuto para la misma cuenta desde la misma IP, 30 por minuto
por IP y 20 por hora para la misma cuenta. Al pasarse responde `429` con
`Retry-After`. Los límites se definen en `AppServiceProvider::registerRateLimiters()`.

### 2. Recuperación de contraseña (públicas por necesidad)

`POST /api/password/forgot` — envía un código al correo personal del usuario. Responde
siempre `200`, exista o no la cuenta, para no revelar quién está registrado.
```json
{ "email": "laura@ejemplo.com" }
```

`POST /api/password/reset` — cambia la contraseña con el código recibido.
```json
{
  "email": "laura@ejemplo.com",
  "code": "123456",
  "password": "nuevaClave123",
  "password_confirmation": "nuevaClave123"
}
```

Límites: 3 solicitudes de código y 6 intentos de cambio cada 10 minutos por cuenta. Además,
el código se invalida solo tras varios intentos fallidos.

---

## 🔒 Rutas Protegidas (Requieren Bearer Token)
Todos los siguientes endpoints requieren que envíes en tus headers:
```http
Authorization: Bearer {tu_token_recibido_en_el_login}
Accept: application/json
Content-Type: application/json
```

### 3. Endpoints Secundarios de Auth
- `POST /api/logout`: Cierra sesión y destruye el token (No necesita body).
- `GET /api/me`: Devuelve la información del usuario en sesión actualmente, sus roles y permisos. (No necesita body).

---

## Mantenimientos Principales (CRUDs)

### 4. Usuarios (`/api/users`)
Ruta administrativa (CRUD) para gestionar todo el personal médico, enfermeras, y administradores.
**POST /api/users**
```json
{
  "first_name": "Juan",
  "last_name": "Pérez",
  "dpi": "1234567890101",
  "phone": "55554444",
  "email": "juan.perez@ejemplo.com",
  "password": "password123",
  "role": "Enfermera", 
  "position": "Médico Especialista",
  "hire_date": "2026-04-01",
  "address": "Ciudad, Zona 1",
  "profile_image": "url-de-foto.jpg",
  "status": "active"
}
```

### 5. Residentes (`/api/residents`)
Gestionar ancianos pacientes.
**POST /api/residents**
```json
{
  "first_name": "Alberto",
  "last_name": "González",
  "dpi": "9876543210101",
  "birth_date": "1945-05-12",
  "gender": "Masculino",
  "room_number": "A-12",
  "admission_date": "2025-10-01",
  "blood_type": "O+",
  "emergency_contact": "Lucía González",
  "emergency_phone": "55556666",
  "notes": "Alérgico al maní.",
  "status": "active"
}
```

### 6. Imágenes de Residente (`/api/resident-images`)
Subir fotos o documentos del residente.
**POST /api/resident-images**
```json
{
  "resident_id": 1,
  "image_path": "ruta/en/storage/foto.png",
  "image_type": "Perfil",
  "uploaded_by": 2
}
```

### 7. Signos Vitales (`/api/resident-vitals`)
Registro diario de signos vitales.
**POST /api/resident-vitals**
```json
{
  "resident_id": 1,
  "weight": 72.5,
  "blood_pressure": "120/80",
  "heart_rate": 75,
  "temperature": 36.8,
  "oxygen_saturation": 98,
  "recorded_by": 2,
  "recorded_at": "2026-04-07 10:30:00"
}
```

### 8. Enfermedades (Catálogo CIE-10) (`/api/diseases`)
Catálogo general de enfermedades existentes.
**POST /api/diseases**
```json
{
  "name": "Hipertensión Arterial",
  "description": "Presión arterial alta crónica.",
  "icd_10_code": "I10"
}
```

### 9. Diagnósticos a Residente (`/api/disease-resident-assignments`)
Asignar una enfermedad a un paciente (diagnóstico).
**POST /api/disease-resident-assignments**
```json
{
  "resident_id": 1,
  "disease_id": 2,
  "diagnosed_at": "2026-04-01",
  "notes": "Diagnosticado por Dr. Juan, requiere monitoreo."
}
```

---

## Farmacia y Recetas

### 10. Medicamentos Base (`/api/medications`)
Catálogo de la farmacia.
**POST /api/medications**
```json
{
  "name": "Ibuprofeno 400mg",
  "description": "Antiinflamatorio.",
  "side_effects": "Náuseas.",
  "contraindications": "Alergias a AINEs."
}
```

### 11. Prescripciones Médicas (`/api/prescriptions`)
Receta asignada a un residente.
**POST /api/prescriptions**
```json
{
  "resident_id": 1,
  "medication_id": 5,
  "prescribed_by": 2,
  "dosage": "1 pastilla",
  "frequency": "Cada 8 horas",
  "start_date": "2026-04-07",
  "end_date": "2026-04-14",
  "instructions": "Con comidas.",
  "status": "active"
}
```

### 12. Horarios de Medicamentos (`/api/medication-schedules`)
Horarios fijos donde toca dar pastillas.
**POST /api/medication-schedules**
```json
{
  "prescription_id": 10,
  "scheduled_time": "14:00:00"
}
```

### 13. Alertas de Medicamentos (`/api/medication-alerts`)
Generación de alertas si toca administrar medicinas.
**POST /api/medication-alerts**
```json
{
  "prescription_id": 10,
  "resident_id": 1,
  "scheduled_time": "2026-04-07 14:00:00",
  "alert_type": "Recordatorio"
}
```

### 14. Logs/Registro de Administración (`/api/medication-logs`)
Historial llenado por enfermeras luego de dar (u omitir) medicamento.
**POST /api/medication-logs**
```json
{
  "schedule_id": 5,
  "administered_by": 3,
  "scheduled_time": "2026-04-07 14:00:00",
  "administered_time": "2026-04-07 14:05:00",
  "status": "Tomado",
  "delay_minutes": 5,
  "error_type": null,
  "administered_dose": "1 pastilla",
  "reason_for_omission": null,
  "notes": "Todo en orden",
  "claimed_by": 3,
  "claimed_at": "2026-04-07 14:05:00"
}
```

---

## Otros y Sistema

### 15. Reportes en PDF (`/api/reports/...`)

Devuelven un archivo PDF (`Content-Type: application/pdf`), no JSON. Todos aceptan el
periodo por query string: `period=day|week|month|year|range` con `date=AAAA-MM-DD`, o
`period=range` con `from` y `to`.

| Endpoint | Qué contiene | Permiso |
|---|---|---|
| `GET /api/reports/residents/{id}/medications` | Medicación, dosis administradas y omisiones de un residente | `view_reports` |
| `GET /api/reports/incidents` | Incidencias clasificadas por tipo | `view_reports` |
| `GET /api/reports/residents` | Listado general de residentes y tratamientos activos | `view_reports` |
| `GET /api/reports/compliance` | Cumplimiento: a tiempo, con retraso y omitidas | `view_management_reports` |
| `GET /api/reports/nurses/{id}/activity` | Actividad y omisiones de una enfermera | `view_management_reports` |

Los dos últimos son **reportes de supervisión**: miden el desempeño del personal, así que
solo los ve quien supervisa (Admin). La enfermera no ve ni el suyo ni el de sus compañeras.

Ejemplo:
```http
GET /api/reports/compliance?period=month&date=2026-09-01
Authorization: Bearer {token}
```

### 16. Notificaciones push (`/api/device-tokens`)

El aviso de medicamento pendiente lo dispara el scheduler (`app:check-pending-medications`),
no el cliente. Lo que la aplicación registra es el dispositivo que debe recibirlo.

**POST /api/device-tokens** — al activar las notificaciones:
```json
{
  "token": "token-FCM-del-dispositivo",
  "platform": "web"
}
```
`platform`: `web`, `android` o `ios`.

**DELETE /api/device-tokens** — al cerrar sesión, para que ese dispositivo deje de recibir avisos.

**POST /api/device-tokens/test** — envía una notificación de prueba al usuario en sesión.

Lo que el usuario ve en pantalla como "notificaciones" son las alertas de medicación:
`GET /api/medication-alerts` (sección 13).

### 17. Registro de Auditoría (`/api/audit-logs`)
Bitácora de movimientos (generalmente llenada automáticamente por Eventos en el backend, no desde el UI manualmente).
**POST /api/audit-logs**
```json
{
  "user_id": 1,
  "action": "UPDATE",
  "table_name": "residents",
  "record_id": 15,
  "old_values": "{\"status\":\"active\"}",
  "new_values": "{\"status\":\"inactive\"}"
}
```

### 18. Otros endpoints

| Endpoint | Para qué |
|---|---|
| `PUT /api/residents/{id}/assigned-nurse` | Asignar la enfermera responsable. Body: `{"assigned_nurse_id": 3}` o `{"assigned_nurse_id": null}` para quitarla. Solo Admin |
| `POST /api/residents/{id}/restore` | Reactivar un residente dado de baja. Permiso `delete_residents` |
| `/api/resident-documents` | Documentos del residente (PDF e imágenes). Se sube con `multipart/form-data` |
| `/api/medication-stock-movements` | Movimientos de inventario. Permiso `manage_inventory` |
| `PUT /api/me` | Actualizar el propio perfil, incluida la foto (`multipart/form-data`) |
| `GET /up` | Chequeo de salud de Laravel, para monitores de disponibilidad |

*(El CRUD de `/api/jobs` se eliminó el 15/09/2026: exponía la cola interna de Laravel, no
era parte del sistema y ningún cliente la usaba.)*
