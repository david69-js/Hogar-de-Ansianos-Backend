# Documentación de la API - Hogar de Ancianos Sor Herminia

Esta documentación describe la estructura esperada (Payloads en JSON) para enviar información (Crear/Actualizar) a los endpoints de la API, basados exactamente en tu archivo de rutas (`routes/api.php`).

## Autenticación (Rutas Públicas)

### 1. Registro Automático (`POST /api/register`)
Se usa para registrar nuevos miembros del personal interno (por defecto se les asigna rol Staff).
```json
{
  "first_name": "Laura",
  "last_name": "Méndez",
  "dpi": "3000400050101",
  "phone": "55551234",
  "email": "laura@ejemplo.com",
  "password": "password123",
  "role": "Enfermera"
}
```

### 2. Inicio de Sesión (`POST /api/login`)
El usuario se loguea para recibir su Token de Acceso (`access_token`).
```json
{
  "email": "laura@ejemplo.com",
  "password": "password123"
}
```

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
  "role": "Doctor", 
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

### 15. Reportes de Residente (`/api/resident-reports`)
**POST /api/resident-reports**
```json
{
  "resident_id": 1,
  "user_id": 3,
  "report_date": "2026-04-07",
  "report_type": "Rutina",
  "description": "El residente durmió bien.",
  "status": "Revisado"
}
```

### 16. Notificaciones (`/api/notifications`)
Sistema de mensajería o notificaciones en app.
**POST /api/notifications**
```json
{
  "resident_id": 1,
  "message": "Es hora del chequeo mensual.",
  "scheduled_for": "2026-04-08 08:00:00",
  "sent_at": null,
  "status": "Pendiente"
}
```

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

### 18. Jobs de Sistema (`/api/jobs`)
*(Nota: Rara vez querrás manipular esta tabla manualmente a través del controlador REST, ya que Laravel la usa automáticamente para encolar trabajos, pero su esquema requiere esto:)*
**POST /api/jobs**
```json
{
  "queue": "default",
  "payload": "{... json interno ...}",
  "attempts": 0,
  "reserved_at": null,
  "available_at": 1690000000,
  "created_at": 1690000000
}
```
