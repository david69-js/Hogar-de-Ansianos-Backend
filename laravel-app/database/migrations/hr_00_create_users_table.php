<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * `users` = personal del hogar (Admin/Enfermera/Staff vía roles Spatie),
     * NO residentes — esos viven en su propia tabla. `status` ("active"/
     * "inactive") es la baja lógica real que usa AuthController::login() para
     * bloquear el acceso; SoftDeletes (deleted_at) es un mecanismo aparte.
     * `sessions` es infraestructura estándar de Laravel que este proyecto no
     * usa (autentica con Sanctum, no con sesiones web). `password_reset_tokens`
     * sí se usa: guarda el código de 6 dígitos hasheado del flujo de
     * recuperación de contraseña (ver PasswordResetController).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->string('dpi')->unique();
            $table->string('phone');
            // Correo institucional: es la identidad de login (por eso unique).
            // Puede ser un buzón que en la práctica nadie revise.
            $table->string('email')->unique();
            // Correo personal real (Gmail, etc.) al que se envía el código para
            // recuperar la contraseña. Sin unique a propósito: dos personas
            // pueden compartir un correo familiar, y no es identidad de login.
            // Si está vacío, el código va al correo institucional.
            $table->string('recovery_email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role');
            $table->string('position')->nullable();
            $table->date('hire_date')->nullable();
            $table->text('address')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('status')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            // Hash del código de 6 dígitos — nunca se guarda en claro, igual que
            // una contraseña: quien lea la base no puede usar el código.
            $table->string('token');
            // Intentos fallidos de ese código. Al llegar al máximo se borra la
            // fila, para que 1,000,000 de combinaciones no sean fuerza-brutables
            // dentro de los 15 minutos de vigencia.
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::enableForeignKeyConstraints();
    }
};
