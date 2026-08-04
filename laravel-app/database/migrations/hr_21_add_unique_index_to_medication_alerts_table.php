<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // El chequeo "¿ya existe un aviso de este tipo?" + el create() no son atómicos:
        // si el comando corre dos veces casi al mismo tiempo (ej. schedule:work en
        // segundo plano y una ejecución manual), ambos procesos pueden pasar el
        // chequeo antes de que cualquiera inserte, duplicando el push. Este índice
        // único convierte el insert en un "lock": solo un proceso logra crear la
        // fila, y el otro recibe un error de duplicado que se puede ignorar.
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->unique(['schedule_id', 'alert_type', 'scheduled_time'], 'medication_alerts_schedule_type_time_unique');
        });
    }

    public function down(): void
    {
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->dropUnique('medication_alerts_schedule_type_time_unique');
        });
    }
};
