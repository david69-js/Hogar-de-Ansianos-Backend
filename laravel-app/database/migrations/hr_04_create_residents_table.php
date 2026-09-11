<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * `residents` = los pacientes del hogar (no personal). Centro del dominio:
     * de aquí cuelgan condiciones médicas, prescripciones y, a través de
     * ellas, todo el ciclo de administración de medicamentos. Baja lógica
     * reversible (softDeletes) — desactivar un residente NO descontinúa sus
     * prescripciones activas automáticamente, es un paso aparte.
     */
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('second_last_name')->nullable();
            $table->string('dpi')->unique()->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('room_number')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('blood_type')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->text('allergies')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->text('notes')->nullable();
            // Enfermera responsable (asignación fija que hace la administradora).
            // Solo prioriza —cualquier enfermera puede seguir registrando dosis de
            // cualquier residente— y decide a quién van las alertas push; ver
            // ResidentController::assignNurse() y CheckPendingMedications.
            $table->foreignId('assigned_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('residents');
        Schema::enableForeignKeyConstraints();
    }
};
