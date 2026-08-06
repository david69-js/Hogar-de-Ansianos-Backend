<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Historial médico / documentos de un residente (expedientes previos, recetas
        // anteriores, informes de laboratorio, etc.) — a diferencia de resident_images,
        // acepta cualquier tipo de archivo (PDF, Word, imágenes de documentos escaneados),
        // y a diferencia de resident_reports (notas de texto internas del staff), es
        // siempre un archivo adjunto real.
        Schema::create('resident_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents')->onDelete('cascade');
            $table->string('title');
            $table->string('document_type')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_documents');
    }
};
