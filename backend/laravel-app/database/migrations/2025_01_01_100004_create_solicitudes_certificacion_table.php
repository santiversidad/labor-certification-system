<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_certificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->string('tipo_certificado', 30);   // TipoCertificadoEnum
            $table->string('estado', 30)->default('pendiente');  // EstadoSolicitudEnum
            $table->boolean('requiere_pago')->default(false);
            $table->boolean('requiere_salario')->default(false);
            $table->text('observaciones')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_certificacion');
    }
};
