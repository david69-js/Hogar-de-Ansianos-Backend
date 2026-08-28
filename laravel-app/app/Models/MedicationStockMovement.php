<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una línea del kardex de inventario de un medicamento: entrada, salida o
 * ajuste. `quantity` es siempre el delta YA CON SIGNO (positivo en entradas,
 * negativo en salidas/ajustes a la baja) — sumar todos los `quantity` de un
 * medicamento reproduce su `stock_quantity` actual sin tener que interpretar
 * `type`. `resulting_stock` guarda el saldo justo después de este movimiento,
 * para poder reconstruir el historial sin recalcular nada.
 * `medication_log_id` solo se llena cuando el movimiento lo generó el sistema
 * automáticamente al marcar una dosis como administrada (ver
 * MedicationLogController::decrementStockForSchedule()) — permite rastrear qué
 * salida de inventario corresponde a qué administración real.
 */
class MedicationStockMovement extends Model
{
    protected $guarded = ['id'];
}
