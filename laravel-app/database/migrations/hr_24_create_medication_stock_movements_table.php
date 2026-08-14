<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medication_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_id')->constrained('medications')->onDelete('cascade');
            $table->string('type'); // entrada | salida | ajuste
            // Delta firmado ya aplicado al stock (entrada > 0, salida < 0, ajuste cualquiera
            // menos 0) — sumar todos los quantity de un medicamento reproduce su stock actual.
            $table->integer('quantity');
            $table->unsignedInteger('resulting_stock');
            $table->text('reason')->nullable();
            // Presente solo cuando el movimiento lo generó automáticamente el sistema al
            // marcar un medicamento como administrado (ver MedicationLogController::store).
            $table->foreignId('medication_log_id')->nullable()->constrained('medication_logs')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('medication_stock_movements');
        Schema::enableForeignKeyConstraints();
    }
};
