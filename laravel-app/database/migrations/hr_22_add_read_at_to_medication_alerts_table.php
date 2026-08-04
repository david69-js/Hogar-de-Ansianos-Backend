<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bandeja de notificaciones compartida por todo el equipo (sin estado de
        // leído por usuario): read_at nulo = no leída todavía por nadie.
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->dateTime('read_at')->nullable()->after('alert_type');
        });
    }

    public function down(): void
    {
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
