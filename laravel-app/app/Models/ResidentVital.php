<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Una medición puntual de signos vitales de un residente (peso, presión,
 * frecuencia cardiaca, temperatura, saturación), capturada desde la sección
 * "Signos Vitales" de la ficha del residente.
 */
class ResidentVital extends Model
{
    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];
}
