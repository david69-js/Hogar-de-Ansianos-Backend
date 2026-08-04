<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->unsignedInteger('stock_quantity')->default(0)->after('dosage_form');
            // Umbral para disparar la alerta de "stock bajo" (null = sin alerta configurada).
            $table->unsignedInteger('minimum_stock')->nullable()->after('stock_quantity');
            // Inventario "simple" (no por lote): representa la caducidad del lote vigente,
            // se actualiza cada vez que se registra una entrada de stock nueva.
            $table->date('expiration_date')->nullable()->after('minimum_stock');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn(['stock_quantity', 'minimum_stock', 'expiration_date']);
        });
    }
};
