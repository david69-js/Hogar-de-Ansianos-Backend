# Documentación de la API - Hogar de Ancianos Sor Herminia

Esta documentación describe la estructura esperada (Payloads en JSON) para enviar información (Crear/Actualizar) a los principales endpoints de la API. 

Todos los endpoints (excepto `/api/login` y `/api/register`) requieren un **Bearer Token** en los headers de la petición:
```http
Authorization: Bearer {tu_token_aqui}
Accept: application/json
Content-Type: application/json
```

---

## 1. Usuarios (`/api/users`)
Ruta para gestionar al personal médico, enfermeras, y administradores.

**POST /api/users**
```json
{
  "first_name": "Juan",
  "last_name": "Pérez",
  "dpi": "1234567890101",
  "phone": "55554444",
  "email": "juan.perez@ejemplo.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "Admin", 
  "position": "Médico General",
  "hire_date": "2026-04-01",
  "address": "Ciudad de Guatemala, Zona 1",
  "emergency_contact": "Maria Pérez",
  "emergency_phone": "55553333",
  "status": "active"
}
```
* **Tipos de datos:** Todos los campos son `string`, a excepción de las fechas que usan formato `YYYY-MM-DD`. `dpi`, `phone` y `role` son obligatorios.

---

## 2. Residentes (`/api/residents`)
Gestiona a los ancianos ingresados en el hogar.

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
  "notes": "Alérgico al maní y requiere asistencia para caminar.",
  "status": "active"
}
```

---

## 3. Signos Vitales (`/api/resident-vitals`)
Registro diario de signos vitales para un residente.

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
* **Tipos de datos:** `weight` y `temperature` son decimales (`numeric`). `heart_rate` y `oxygen_saturation` son enteros (`integer`). `resident_id` es obligatorio.

---

## 4. Medicamentos Base (`/api/medications`)
Catálogo general de medicinas disponibles.

**POST /api/medications**
```json
{
  "name": "Ibuprofeno 400mg",
  "description": "Antiinflamatorio no esteroideo utilizado para el alivio del dolor.",
  "side_effects": "Malestar estomacal, náuseas.",
  "contraindications": "No tomar si es alérgico a los AINE o tiene úlceras."
}
```

---

## 5. Recetas Médicas / Prescripciones (`/api/prescriptions`)
Receta asignada a un residente específico por un doctor.

**POST /api/prescriptions**
```json
{
  "resident_id": 1,
  "medication_id": 5,
  "prescribed_by": 2,
  "dosage": "1 pastilla (400mg)",
  "frequency": "Cada 8 horas",
  "start_date": "2026-04-07",
  "end_date": "2026-04-14",
  "instructions": "Tomar después de comer para evitar malestar estomacal.",
  "status": "active"
}
```
* **Tipos de datos:** Requiere los IDs relacionales (`resident_id`, `medication_id`, `prescribed_by` el cual es un user_id validos).

---

## 6. Enfermedades (`/api/diseases`)
Catálogo de enfermedades (CIE-10).

**POST /api/diseases**
```json
{
  "name": "Hipertensión Arterial",
  "description": "Presión arterial alta crónica.",
  "icd_10_code": "I10"
}
```

---

## 7. Reportes de Residente (`/api/resident-reports`)
Reportes diarios o de incidentes del personal de enfermería o médico.

**POST /api/resident-reports**
```json
{
  "resident_id": 1,
  "user_id": 3,
  "report_date": "2026-04-07",
  "report_type": "Rutina",
  "description": "El residente durmió bien pero presenta poco apetito en el desayuno.",
  "status": "active"
}
```

---

## Resumen de Reglas Generales
1. **Atributos de Relación**: Todo lo que termine en `_id` (ej. `resident_id`) espera un número entero (ID) válido que ya exista en esa tabla.
2. **Fechas**: Usar formato ISO 8601 (`YYYY-MM-DD` para `date` y `YYYY-MM-DD HH:MM:SS` para `datetime/timestamp`).
3. **Paginación**: Todos los endpoints `GET` devuelven colecciones envueltas en un objeto `"data": [...]` por defecto si utilizas API Resources de Laravel.
