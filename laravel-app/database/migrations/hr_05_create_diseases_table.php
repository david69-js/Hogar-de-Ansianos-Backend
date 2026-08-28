<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Catálogo de condiciones médicas (CIE-10). Se asigna a un residente a
     * través de la tabla intermedia disease_resident_assignments, no aquí.
     */
    public function up(): void
    {
        Schema::create('diseases', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('icd_10_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('diseases');
        Schema::enableForeignKeyConstraints();
    }
};
