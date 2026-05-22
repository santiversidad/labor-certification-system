<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rangos_salariales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('grado', 5);
            $table->smallInteger('vigencia_anio');
            $table->decimal('salario_basico', 14, 2);
            $table->string('moneda', 3)->default('COP');
            $table->text('observaciones')->nullable();
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['codigo', 'grado', 'vigencia_anio'], 'rangos_salariales_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rangos_salariales');
    }
};
