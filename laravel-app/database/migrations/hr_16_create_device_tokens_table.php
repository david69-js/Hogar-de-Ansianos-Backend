<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token de Firebase Cloud Messaging de un DISPOSITIVO/navegador concreto,
     * no de una sesión de login — por eso `token` es único por fila y
     * sobrevive a un logout normal si nadie llama a DELETE explícitamente.
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('token')->unique();
            $table->string('platform')->default('web'); // web | android | ios
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('device_tokens');
        Schema::enableForeignKeyConstraints();
    }
};
