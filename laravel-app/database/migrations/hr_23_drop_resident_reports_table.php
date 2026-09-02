<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabla de notas/bitácora libre del staff sobre un residente: tenía CRUD
     * pero ninguna pantalla la usó nunca. Se elimina en vez de dejarla sin uso.
     */
    public function up(): void
    {
        Schema::dropIfExists('resident_reports');
    }

    public function down(): void
    {
        Schema::create('resident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('report_type')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
