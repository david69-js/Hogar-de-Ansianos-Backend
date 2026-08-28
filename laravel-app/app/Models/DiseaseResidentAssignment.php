<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla intermedia: qué condición médica (Disease) tiene diagnosticada qué
 * residente, desde cuándo y con qué notas. Un mismo par residente+condición no
 * puede asignarse dos veces (validado en el controlador, no por índice único
 * en BD). Se puede "retirar" (destroy = hard delete, sin softDeletes) si el
 * diagnóstico se registró por error.
 */
class DiseaseResidentAssignment extends Model
{
    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];

    // La tabla no tiene columnas created_at/updated_at (ver migración).
    public $timestamps = false;
}
