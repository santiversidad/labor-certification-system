<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funcionario_cargo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_id')->constrained('funcionarios')->cascadeOnDelete();
            $table->foreignId('cargo_id')->constrained('cargos')->cascadeOnDelete();
            $table->string('tipo_vinculacion', 40);
            $table->string('naturaleza_cargo', 60);
            $table->boolean('es_cargo_base')->default(false);
            $table->boolean('es_encargo')->default(false);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->foreignId('acto_administrativo_id')->nullable()->constrained('actuaciones_administrativas')->nullOnDelete();
            $table->decimal('salario_override', 14, 2)->nullable();
            $table->timestamps();

            $table->index(['funcionario_id', 'fecha_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionario_cargo');
    }
};
