<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Las alertas de medicamento pendiente son por residente (resident_id); las nuevas
        // alertas de inventario (stock bajo / por vencer / vencido) son sobre el catálogo,
        // sin residente asociado, por eso necesitan su propia referencia.
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->foreignId('medication_id')->nullable()->after('resident_id')
                ->constrained('medications')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->dropForeign(['medication_id']);
            $table->dropColumn('medication_id');
        });
    }
};
