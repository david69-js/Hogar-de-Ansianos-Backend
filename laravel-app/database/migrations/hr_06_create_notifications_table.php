<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabla de notificaciones del modelo original — quedó sin uso real: el
     * sistema de avisos en producción es medication_alerts + Firebase Cloud
     * Messaging (ver hr_14_create_medication_alerts_table). Se conserva por
     * estar en el modelo entidad-relación documentado, pero nada la escribe.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->nullable()->constrained('residents')->onDelete('cascade');
            $table->text('message')->nullable();
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('notifications');
        Schema::enableForeignKeyConstraints();
    }
};
