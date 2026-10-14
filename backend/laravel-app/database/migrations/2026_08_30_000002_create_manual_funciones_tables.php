<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manuales_funciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('manual_funciones_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_funciones_id')->constrained('manuales_funciones')->restrictOnDelete();
            $table->string('version', 80);
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->string('acto_tipo', 80);
            $table->string('acto_numero', 80);
            $table->date('acto_fecha');
            $table->text('acto_referencia')->nullable();
            $table->string('estado', 30)->default('borrador');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['manual_funciones_id', 'version'], 'manual_version_identificador_unique');
            $table->index(['estado', 'vigencia_desde', 'vigencia_hasta'], 'manual_version_vigencia_index');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE manual_funciones_versiones
            ADD CONSTRAINT manual_version_fechas_check
            CHECK (vigencia_hasta IS NULL OR vigencia_hasta >= vigencia_desde)
        SQL);

        Schema::create('manual_cargo_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_funciones_version_id')->constrained('manual_funciones_versiones')->restrictOnDelete();
            $table->foreignId('cargo_id')->constrained('cargos')->restrictOnDelete();
            $table->text('proposito_principal');
            $table->text('requisitos')->nullable();
            $table->timestamps();

            $table->unique(
                ['manual_funciones_version_id', 'cargo_id'],
                'manual_version_cargo_unique'
            );
        });

        Schema::create('manual_funciones_esenciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_cargo_version_id')->constrained('manual_cargo_versiones')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->text('descripcion');
            $table->timestamps();

            $table->unique(['manual_cargo_version_id', 'orden'], 'manual_funcion_orden_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_funciones_esenciales');
        Schema::dropIfExists('manual_cargo_versiones');
        Schema::dropIfExists('manual_funciones_versiones');
        Schema::dropIfExists('manuales_funciones');
    }
};
