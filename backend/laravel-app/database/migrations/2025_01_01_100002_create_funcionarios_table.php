<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo_documento', 10)->default('CC');
            $table->string('numero_documento', 20)->unique();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('correo_institucional', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('estado', 20)->default('activo');   // EstadoFuncionarioEnum
            $table->date('fecha_ingreso')->nullable();
            $table->date('fecha_retiro')->nullable();
            $table->string('dependencia', 150)->nullable();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionarios');
    }
};
