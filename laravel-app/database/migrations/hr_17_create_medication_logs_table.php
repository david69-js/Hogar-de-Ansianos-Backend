<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('medication_schedules')->onDelete('set null');
            $table->foreignId('administered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('scheduled_time')->nullable();
            $table->dateTime('administered_time')->nullable();
            $table->string('status')->nullable();
            $table->integer('delay_minutes')->nullable();
            $table->string('error_type')->nullable();
            $table->string('administered_dose')->nullable();
            $table->text('reason_for_omission')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->dateTime('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_logs');
    }
};
