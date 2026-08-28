<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo de medicamentos + inventario simple (un stock por medicamento, no
 * por lote). `stock_quantity`/`expiration_date` nunca se editan directo desde
 * el formulario del catálogo — solo cambian a través de un movimiento en
 * MedicationStockMovement, para que quede rastro auditable de cada cambio de
 * stock (ver MedicationController::store/update, que explícitamente no los
 * acepta). Un medicamento en uso en alguna prescripción no puede eliminarse.
 */
class Medication extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];
}
