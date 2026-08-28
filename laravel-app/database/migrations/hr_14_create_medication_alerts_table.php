<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Registro de avisos ya enviados — dos tipos comparten tabla: recordatorios
     * de dosis (por resident_id+schedule_id, generados cada minuto por
     * app:check-pending-medications) y alertas de inventario (por
     * medication_id, generadas una vez al día por app:check-medication-stock).
     */
    public function up(): void
    {
        Schema::create('medication_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->onDelete('cascade');
            $table->foreignId('schedule_id')->nullable()->constrained('medication_schedules')->onDelete('cascade');
            $table->foreignId('resident_id')->nullable()->constrained('residents')->onDelete('cascade');
            // Las alertas de medicamento pendiente son por residente; las de inventario
            // (stock bajo / por vencer / vencido) son sobre el catálogo, sin residente.
            $table->foreignId('medication_id')->nullable()->constrained('medications')->onDelete('cascade');
            $table->dateTime('scheduled_time')->nullable();
            $table->string('alert_type')->nullable();
            // Bandeja compartida por todo el equipo (sin estado de leído por usuario):
            // read_at nulo = no leída todavía por nadie.
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            // Dedup por horario exacto: convierte el insert en un "lock" atómico para
            // que dos ejecuciones concurrentes del comando de avisos no dupliquen el push.
            $table->unique(['schedule_id', 'alert_type', 'scheduled_time'], 'medication_alerts_schedule_type_time_unique');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('medication_alerts');
        Schema::enableForeignKeyConstraints();
    }
};
