<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabla del modelo entidad-relación original que nunca se usó: el sistema
     * de avisos real en producción es medication_alerts + Firebase Cloud
     * Messaging (ver hr_14_create_medication_alerts_table). Se elimina en vez
     * de dejarla sin uso.
     */
    public function up(): void
    {
        Schema::dropIfExists('notifications');
    }

    public function down(): void
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
};
