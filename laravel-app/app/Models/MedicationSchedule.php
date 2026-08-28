<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un horario recurrente diario (solo HH:MM, sin fecha) dentro de una
 * prescripción — ej. "08:00" y "20:00" son dos filas de la misma prescripción.
 * Se repite TODOS los días mientras la prescripción esté vigente/activa; no
 * hay pauta de días alternos o semanales (limitación conocida, ver el
 * documento de análisis del proyecto). No se puede repetir el mismo horario
 * dos veces dentro de la misma prescripción (índice único), pero dos
 * prescripciones distintas sí pueden compartir hora sin problema.
 */
class MedicationSchedule extends Model
{
    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MedicationLog::class, 'schedule_id');
    }
}
