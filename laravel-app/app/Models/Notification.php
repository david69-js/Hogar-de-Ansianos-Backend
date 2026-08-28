<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla original de notificaciones (mensaje + programación de envío por
 * residente). En la práctica quedó sin uso: el sistema de avisos real que sí
 * está en producción es MedicationAlert + Firebase Cloud Messaging (ver
 * app:check-pending-medications y app:check-medication-stock). Se conserva
 * porque el modelo entidad-relación original la contempla, pero ninguna
 * pantalla ni comando escribe en ella hoy.
 */
class Notification extends Model
{
    protected $guarded = ['id'];
}
