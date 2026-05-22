<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_certificacion_id')->constrained('solicitudes_certificacion')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->string('codigo_unico', 64)->unique();
            $table->string('archivo_pdf_path', 500)->nullable();
            $table->string('hash_pdf', 128)->nullable();
            $table->timestamp('fecha_generacion');
            $table->foreignId('generado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estado', 20)->default('vigente');  // EstadoCertificadoEnum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};
