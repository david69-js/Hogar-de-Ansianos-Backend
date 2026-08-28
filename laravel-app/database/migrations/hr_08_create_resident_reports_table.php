<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Nota/bitácora libre del staff sobre un residente (report_type +
     * description en texto) — distinta de los reportes PDF de
     * ReportController. Tabla y CRUD existen, sin pantalla que los use aún.
     */
    public function up(): void
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

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('resident_reports');
        Schema::enableForeignKeyConstraints();
    }
};
