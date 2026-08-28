<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabla intermedia: qué condición médica (diseases) tiene diagnosticada
     * qué residente, desde cuándo y con qué notas. Sin timestamps a propósito
     * (ver el modelo, `$timestamps = false`) y sin softDeletes: "retirar" una
     * condición es un borrado físico.
     */
    public function up(): void
    {
        Schema::create('disease_resident_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->onDelete('cascade');
            $table->foreignId('disease_id')->constrained('diseases')->onDelete('cascade');
            $table->date('diagnosed_at')->nullable();
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('disease_resident_assignments');
        Schema::enableForeignKeyConstraints();
    }
};
