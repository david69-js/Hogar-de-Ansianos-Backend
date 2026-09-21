<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * El listado y la ficha muestran la foto del residente. Antes no salía en
     * ninguna respuesta de /residents — vivía solo en /resident-images — así que
     * la interfaz pintaba siempre el ícono genérico aunque el residente tuviera
     * foto cargada.
     */
    protected $appends = [
        'profile_image_url',
    ];

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

    /**
     * La foto más reciente del álbum, que es la que hace de foto de perfil.
     *
     * latestOfMany() lo resuelve con una subconsulta, así no hay que traer el
     * álbum entero de cada residente solo para quedarse con una foto.
     */
    public function latestImage(): HasOne
    {
        return $this->hasOne(ResidentImage::class)->latestOfMany();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ResidentDocument::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    // Enfermera responsable. assigned_nurse_id queda fuera de $fillable a
    // propósito: solo se cambia por ResidentController::assignNurse() (Admin),
    // nunca por el PUT general del residente.
    public function assignedNurse(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_nurse_id');
    }

    /**
     * URL de la foto de perfil, o null si el residente no tiene ninguna. La
     * firma temporal la resuelve ResidentImage::full_url (el bucket es privado).
     */
    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->latestImage?->full_url;
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
