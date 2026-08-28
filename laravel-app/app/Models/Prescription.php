<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La orden de un medicamento para un residente: dosis, vía, vigencia
 * (start_date/end_date) e instrucciones. NO es una dosis en sí — es la
 * "plantilla" de la que cuelgan uno o más MedicationSchedule (horarios que se
 * repiten todos los días mientras la prescripción esté vigente y activa).
 * `is_active` es la forma real de "descontinuar" (ver PrescriptionController::
 * destroy(), que hace update, no delete) — deliberadamente distinta del
 * SoftDeletes de la tabla, para diferenciar "descontinuada por el médico" de
 * "eliminada por error".
 */
class Prescription extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(MedicationSchedule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
