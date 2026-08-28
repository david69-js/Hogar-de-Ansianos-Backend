<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Catálogo de medicamentos + inventario simple (un stock por medicamento,
     * no por lote). `stock_quantity`/`expiration_date` solo cambian vía
     * medication_stock_movements (ver esa tabla) — el catálogo nunca los edita
     * directo, para que quede rastro auditable de cada cambio de stock.
     */
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->nullable();
            $table->text('description')->nullable();
            $table->string('dosage_form')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            // Umbral para la alerta de "stock bajo" (null = sin alerta configurada).
            $table->unsignedInteger('minimum_stock')->nullable();
            // Inventario simple (no por lote): caducidad del lote vigente, se actualiza
            // cada vez que se registra una entrada de stock nueva.
            $table->date('expiration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('medications');
        Schema::enableForeignKeyConstraints();
    }
};
