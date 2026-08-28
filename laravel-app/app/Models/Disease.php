<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de condiciones médicas (CIE-10). No se asigna directo a un
 * residente — eso lo hace DiseaseResidentAssignment (tabla intermedia con su
 * propia fecha de diagnóstico y notas).
 */
class Disease extends Model
{
    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];
}
