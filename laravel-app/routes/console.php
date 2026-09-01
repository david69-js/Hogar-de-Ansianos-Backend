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

// Respaldo diario de la base de datos (solo BD, no archivos — ver config/backup.php)
// a Cloudflare R2, en horario de bajo tráfico. clean corre antes que run para no
// borrar el backup recién creado si "keep_all_backups_for_days" ya se cumplió justo
// ese día; monitor corre al final y dispara la notificación por correo si algo
// falló o el último backup quedó viejo/pesado.
Schedule::command('backup:clean')->dailyAt('02:00');
Schedule::command('backup:run --only-db')->dailyAt('02:15');
Schedule::command('backup:monitor')->dailyAt('03:00');
