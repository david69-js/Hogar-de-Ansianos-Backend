<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // El dedup de avisos antes solo se hacía por prescription_id + fecha, lo que
        // mezclaba horarios distintos de una misma prescripción (ej. 08:00 y 20:00):
        // el aviso del primero bloqueaba el del segundo. Con schedule_id se dedupea
        // por horario exacto.
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('prescription_id')
                ->constrained('medication_schedules')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('medication_alerts', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropColumn('schedule_id');
        });
    }
};
