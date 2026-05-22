<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_soportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_certificacion_id')->constrained('solicitudes_certificacion')->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->string('archivo_path', 500);
            $table->string('archivo_original_nombre', 255);
            $table->string('estado', 20)->default('pendiente');   // EstadoPagoEnum
            $table->text('observaciones')->nullable();
            $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_soportes');
    }
};
