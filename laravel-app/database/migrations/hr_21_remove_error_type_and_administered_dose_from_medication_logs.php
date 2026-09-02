<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * error_type y administered_dose nunca se llegaron a usar: se decidió no
     * tipificar la incidencia de una dosis omitida (solo motivo en texto
     * libre, ver medication_logs.reason_for_omission) porque en la práctica
     * el caso de "dosis incorrecta" es raro y no justificaba el campo.
     */
    public function up(): void
    {
        Schema::table('medication_logs', function (Blueprint $table) {
            $table->dropColumn(['error_type', 'administered_dose']);
        });
    }

    public function down(): void
    {
        Schema::table('medication_logs', function (Blueprint $table) {
            $table->string('error_type')->nullable();
            $table->string('administered_dose')->nullable();
        });
    }
};
