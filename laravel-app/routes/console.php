<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revisa cada minuto si algún medicamento quedó pendiente y avisa por push.
Schedule::command('app:check-pending-medications')->everyMinute();

// Revisa el inventario una vez al día (stock bajo, próximo a vencer, vencido).
Schedule::command('app:check-medication-stock')->dailyAt('07:00');
