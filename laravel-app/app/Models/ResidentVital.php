<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una medición puntual de signos vitales de un residente (peso, presión,
 * frecuencia cardiaca, temperatura, saturación). La tabla y el endpoint CRUD
 * (ResidentVitalController) existen y funcionan, pero ninguna pantalla del
 * frontend los usa todavía — no hay dónde capturarlos ni verlos.
 */
class ResidentVital extends Model
{
    protected $guarded = ['id'];
}
