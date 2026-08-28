<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de un aviso ya enviado — de dos tipos distintos que comparten tabla:
 * (1) recordatorios de dosis (alert_type: reminder_before/due_now/reminder_delayed),
 *     ligados a resident_id + schedule_id, generados por el comando
 *     app:check-pending-medications (corre cada minuto);
 * (2) alertas de inventario (alert_type: low_stock/expiring_soon/expired),
 *     ligadas a medication_id (sin resident_id), generadas por
 *     app:check-medication-stock (corre una vez al día).
 * No es solo un historial: el índice único (schedule_id, alert_type,
 * scheduled_time) es lo que evita mandar el mismo push dos veces si el comando
 * se ejecuta dos veces casi al mismo tiempo — el INSERT actúa como candado.
 * read_at nulo = todavía no la vio nadie en la bandeja de Notificaciones.
 */
class MedicationAlert extends Model
{
    protected $guarded = ['id'];
}
