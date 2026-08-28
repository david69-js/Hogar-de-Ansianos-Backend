<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un residente del hogar (el paciente, no un miembro del personal). Es el
 * centro del dominio: de aquí cuelgan condiciones médicas (vía
 * DiseaseResidentAssignment), prescripciones y, a través de ellas, todo el
 * ciclo de administración de medicamentos. Baja lógica reversible
 * (SoftDeletes): ResidentController::index() siempre devuelve también los
 * desactivados para poder reactivarlos, pero Calendario/Dashboard los excluyen
 * explícitamente al armar la lista de dosis pendientes del día.
 */
class Resident extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'dpi',
        'birth_date',
        'gender',
        'room_number',
        'admission_date',
        'blood_type',
        'weight',
        'height',
        'allergies',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(ResidentImage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ResidentDocument::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }
}
