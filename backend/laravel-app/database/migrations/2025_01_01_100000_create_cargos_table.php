<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('grado', 5);
            $table->string('denominacion', 150);
            $table->string('nivel', 60)->nullable();
            $table->string('dependencia', 150)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['codigo', 'grado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargos');
    }
};
