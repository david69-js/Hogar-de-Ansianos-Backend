<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resident_vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->onDelete('cascade');
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('blood_pressure')->nullable();
            $table->integer('heart_rate')->nullable();
            $table->decimal('temperature', 8, 2)->nullable();
            $table->integer('oxygen_saturation')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_vitals');
    }
};
