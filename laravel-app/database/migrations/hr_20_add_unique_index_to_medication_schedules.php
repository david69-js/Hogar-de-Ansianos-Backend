<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Evita repetir el mismo horario dos veces DENTRO de una misma prescripción
        // (ej. Metformina 08:00 y 08:00 por error de captura). No bloquea que dos
        // prescripciones distintas compartan hora — eso es normal (varios
        // medicamentos administrados juntos).
        Schema::table('medication_schedules', function (Blueprint $table) {
            $table->unique(['prescription_id', 'scheduled_time'], 'medication_schedules_prescription_time_unique');
        });
    }

    public function down(): void
    {
        Schema::table('medication_schedules', function (Blueprint $table) {
            $table->dropUnique('medication_schedules_prescription_time_unique');
        });
    }
};
