<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nota/bitácora libre sobre un residente (report_type + description), distinta
 * de los reportes PDF de ReportController — esto es texto interno del staff,
 * no un documento generado. La tabla y su CRUD (ResidentReportController)
 * existen y funcionan, pero ninguna pantalla del frontend los usa todavía.
 */
class ResidentReport extends Model
{
    protected $guarded = ['id'];
}
