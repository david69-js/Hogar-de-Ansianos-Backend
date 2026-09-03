<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * El kardex real de administración: una fila por cada vez que se marca
     * una dosis "administered" o "missed". schedule_id+scheduled_time
     * identifican la ocurrencia exacta (un mismo schedule_id se reutiliza
     * todos los días de una prescripción) — de ahí el índice único al final.
     * Si nadie registra una dosis, simplemente no hay fila (ver
     * ReportController::findMissingDoses() para cómo se detecta eso en los
     * reportes).
     */
    public function up(): void
    {
        Schema::create('medication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('medication_schedules')->onDelete('set null');
            $table->foreignId('administered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('scheduled_time')->nullable();
            $table->dateTime('administered_time')->nullable();
            $table->string('status')->nullable();
            $table->integer('delay_minutes')->nullable();
            // No se tipifica la incidencia de una dosis omitida: solo el motivo en
            // texto libre. En la práctica el caso de "dosis incorrecta" es raro y no
            // justificaba columnas propias (error_type/administered_dose).
            $table->text('reason_for_omission')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('claimed_at')->nullable();
            $table->timestamps();

            // Evita que dos pantallas (Dashboard y Calendario) o dos dispositivos
            // registren dos veces la misma dosis por una condición de carrera.
            $table->unique(['schedule_id', 'scheduled_time'], 'medication_logs_schedule_scheduled_time_unique');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('medication_logs');
        Schema::enableForeignKeyConstraints();
    }
};
