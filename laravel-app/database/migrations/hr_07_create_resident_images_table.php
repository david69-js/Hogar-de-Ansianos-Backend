<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Fotos de un residente. El archivo real vive en storage (R2/local); esta
     * fila solo guarda la ruta — ResidentImage::getFullUrlAttribute() genera
     * la URL firmada temporal que consume el frontend.
     */
    public function up(): void
    {
        Schema::create('resident_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->onDelete('cascade');
            $table->string('image_path');
            $table->string('image_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('resident_images');
        Schema::enableForeignKeyConstraints();
    }
};
