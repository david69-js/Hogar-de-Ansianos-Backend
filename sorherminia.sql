Table users {
  id bigint [pk, increment]
  first_name varchar
  last_name varchar
  dpi varchar [unique]  // ✅ Agregado unique
  phone varchar
  email varchar [unique]
  password varchar
  role enum('admin', 'nurse', 'doctor', 'supervisor')
  position varchar
  hire_date date
  address text
  profile_image varchar
  status varchar  // 'active', 'inactive', 'suspended'
  created_at timestamp
  updated_at timestamp
  last_login_at timestamp
  deleted_at timestamp [null]  // ✅ Agregado soft delete
}

Table audit_logs {
  id bigint [pk, increment]
  user_id bigint
  action varchar  // 'create', 'update', 'delete', 'login', 'logout'
  table_name varchar
  record_id bigint
  old_values text
  new_values text
  created_at timestamp
}

Table password_reset_tokens {
  email varchar [pk]
  token varchar
  created_at timestamp
}

Table residents {
  id bigint [pk, increment]
  first_name varchar
  last_name varchar
  dpi varchar [unique]  // ✅ Agregado unique
  birth_date date
  gender varchar  // 'M', 'F', 'Otro'
  room_number varchar
  admission_date date
  blood_type varchar  // 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
  weight decimal
  height decimal
  allergies text
  emergency_contact_name varchar
  emergency_contact_phone varchar
  emergency_contact_relation varchar
  notes text
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp [null]  // ✅ Agregado soft delete
}

Table resident_vitals {
  id bigint [pk, increment]
  resident_id bigint
  weight decimal
  blood_pressure varchar  // '120/80'
  heart_rate int
  temperature decimal
  oxygen_saturation int
  recorded_by bigint
  recorded_at datetime
}

Table resident_images {
  id bigint [pk, increment]
  resident_id bigint
  image_path varchar
  image_type varchar  // 'profile', 'medical', 'document'
  uploaded_by bigint
  created_at timestamp
}

Table disease_resident_assignments {
  id bigint [pk, increment]
  resident_id bigint
  disease_id bigint
  diagnosed_at date
  notes text
}

Table diseases {
  id bigint [pk, increment]
  name varchar [unique]  // ✅ Agregado unique
  description text
  icd_10_code varchar  // ✅ Agregado código ICD-10 (estándar internacional)
}

Table resident_reports {
  id bigint [pk, increment]
  resident_id bigint
  created_by bigint
  report_type varchar  // 'incident', 'progress', 'medical', 'behavioral'
  description text
  created_at timestamp
}

Table medications {
  id bigint [pk, increment]
  name varchar [unique]  // ✅ Agregado unique
  description text
  dosage_form varchar  // 'tablet', 'capsule', 'syrup', 'injection', 'cream'
  created_at timestamp
  updated_at timestamp
}

Table prescriptions {
  id bigint [pk, increment]
  resident_id bigint
  medication_id bigint
  dosage varchar  // '500mg', '10ml', '1 tableta'
  frequency varchar  // 'Cada 8 horas', '3 veces al día', 'Antes de dormir'
  administration_route varchar  // ✅ NUEVO: 'oral', 'IV', 'IM', 'tópica'
  start_date date
  end_date date [null]
  instructions text
  created_by bigint
  is_active boolean [default: true]  // ✅ NUEVO
  created_at timestamp
  updated_at timestamp
  deleted_at timestamp [null]  // ✅ Agregado soft delete
}

Table medication_schedules {
  id bigint [pk, increment]
  prescription_id bigint
  scheduled_time time  // '08:00:00', '14:00:00', '20:00:00'
  created_at timestamp
}

Table medication_logs {
  id bigint [pk, increment]
  schedule_id bigint  // ✅ CAMBIADO de prescription_id a schedule_id
  administered_by bigint
  scheduled_time datetime
  administered_time datetime [null]
  status varchar  // 'administered', 'omitted', 'refused', 'delayed'
  delay_minutes int [null]
  error_type varchar [null]  // 'wrong_dose', 'wrong_time', 'wrong_medication', 'wrong_route', 'omission'
  administered_dose varchar [null]  // Dosis REAL administrada (puede diferir de la prescrita)
  reason_for_omission text [null]  // ✅ NUEVO
  notes text [null]
  claimed_by bigint [null]
  claimed_at datetime [null]
  created_at timestamp
}

Table notifications {
  id bigint [pk, increment]
  resident_id bigint
  message text
  scheduled_for datetime
  sent_at datetime [null]
  status varchar  // 'pending', 'sent', 'failed'
  created_at timestamp
}

Table medication_alerts {
  id bigint [pk, increment]
  prescription_id bigint
  resident_id bigint
  scheduled_time datetime
  alert_type varchar  // 'upcoming', 'overdue', 'missed'
  created_at timestamp
}
// Stock actual de cada medicamento
Table medication_inventory {
  id bigint [pk, increment]
  medication_id bigint [unique]  // Un registro por medicamento
  current_stock decimal          // Unidades disponibles actualmente
  unit varchar                   // 'tabletas', 'ml', 'ampollas', 'sobres'
  minimum_stock decimal          // Umbral mínimo antes de generar alerta
  last_updated_by bigint
  updated_at timestamp
}

// Cada movimiento que afecta el stock
Table inventory_movements {
  id bigint [pk, increment]
  medication_id bigint
  movement_type varchar    // 'ingreso', 'consumo', 'ajuste', 'vencimiento', 'perdida'
  quantity decimal         // Positivo = entrada, Negativo = salida
  reason text              // Descripción del movimiento
  medication_log_id bigint [null]  // Si fue por administración, referencia al log
  performed_by bigint
  created_at timestamp
}

Ref: medication_inventory.medication_id > medications.id
Ref: medication_inventory.last_updated_by > users.id
Ref: inventory_movements.medication_id > medications.id
Ref: inventory_movements.medication_log_id > medication_logs.id
Ref: inventory_movements.performed_by > users.id

// ✅ RELACIONES CORREGIDAS
Ref: audit_logs.user_id > users.id
Ref: prescriptions.resident_id > residents.id
Ref: prescriptions.medication_id > medications.id
Ref: prescriptions.created_by > users.id
Ref: medication_schedules.prescription_id > prescriptions.id
Ref: medication_alerts.prescription_id > prescriptions.id
Ref: medication_alerts.resident_id > residents.id

// ✅ CORREGIDO: Ahora apunta a schedule_id en lugar de prescription_id
Ref: medication_logs.schedule_id > medication_schedules.id
Ref: medication_logs.administered_by > users.id
Ref: medication_logs.claimed_by > users.id

Ref: resident_reports.resident_id > residents.id
Ref: resident_reports.created_by > users.id
Ref: resident_vitals.resident_id > residents.id
Ref: resident_vitals.recorded_by > users.id
Ref: notifications.resident_id > residents.id
Ref: resident_images.resident_id > residents.id
Ref: resident_images.uploaded_by > users.id

// ✅ CORREGIDO: Ahora apunta a diseases.id en lugar de users.id
Ref: disease_resident_assignments.disease_id > diseases.id
Ref: disease_resident_assignments.resident_id > residents.id