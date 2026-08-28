<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Registro de auditoría: quién hizo qué acción sobre qué tabla/registro y
     * los valores antes/después (JSON como texto). Solo la escribe
     * App\Observers\AuditableObserver — ver también AuditLogController, que a
     * propósito solo expone lectura.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action')->nullable();
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('audit_logs');
        Schema::enableForeignKeyConstraints();
    }
};
