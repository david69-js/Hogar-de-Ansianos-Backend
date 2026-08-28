<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Un horario recurrente diario (solo HH:MM, sin fecha) dentro de una
     * prescripción — se repite todos los días mientras la prescripción esté
     * vigente/activa. Creada antes que medication_alerts a propósito: esta
     * necesita existir primero para que la FK schedule_id de esa tabla sea
     * válida.
     */
    public function up(): void
    {
        Schema::create('medication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->onDelete('cascade');
            $table->time('scheduled_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('medication_schedules');
        Schema::enableForeignKeyConstraints();
    }
};
