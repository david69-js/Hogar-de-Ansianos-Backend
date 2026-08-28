<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El kardex real: una fila por cada vez que alguien marca una dosis programada
 * como "administered" o "missed" (nunca se genera sola — si nadie la registra,
 * simplemente no hay fila, ver ReportController::findMissingDoses() para cómo
 * se detectan esas omisiones silenciosas en los reportes).
 *
 * schedule_id + scheduled_time identifican la ocurrencia exacta de la dosis
 * (un mismo schedule_id se reutiliza todos los días de una prescripción, así que
 * es esta combinación, no el schedule_id solo, la que hace única a una dosis —
 * de ahí el índice único que evita registrarla dos veces, ver migración).
 * delay_minutes lo calcula el servidor a partir de scheduled_time/administered_time,
 * nunca se confía en lo que mande el cliente. administered_by es el responsable
 * para efectos de reportes y trazabilidad.
 */
class MedicationLog extends Model
{
    protected $guarded = ['id'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MedicationSchedule::class, 'schedule_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
