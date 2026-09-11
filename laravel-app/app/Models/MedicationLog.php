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

    /**
     * Tipos de incidencia (columna incident_type) y su etiqueta para pantallas y
     * reportes. Son las categorías del instrumento de observación del anexo de
     * la tesis ("Clasificación del evento observado"), más la reacción adversa
     * que el Reporte Mensual de Incidencias lista como tipo propio.
     */
    public const INCIDENT_TYPES = [
        'omision' => 'Omisión de dosis',
        'medicamento_incorrecto' => 'Medicamento incorrecto',
        'dosis_incorrecta' => 'Dosis incorrecta',
        'horario_incorrecto' => 'Horario incorrecto',
        'duplicidad' => 'Duplicidad de administración',
        'registro_incompleto' => 'Registro incompleto',
        'reaccion_adversa' => 'Reacción o malestar del residente',
        'otro' => 'Otro',
    ];

    public function incidentLabel(): ?string
    {
        return $this->incident_type ? (self::INCIDENT_TYPES[$this->incident_type] ?? $this->incident_type) : null;
    }

    // Confirmaciones de dosis e incidencias en el registro de auditoría (ver el
    // comentario de AuditableObserver sobre por qué este modelo sí se observa).
    protected static function booted(): void
    {
        static::observe(\App\Observers\AuditableObserver::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MedicationSchedule::class, 'schedule_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
