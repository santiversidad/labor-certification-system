<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Missing documentary dates remain unknown; publication validates them separately.
        Schema::table('manual_funciones_versiones', function (Blueprint $table) {
            $table->date('vigencia_desde')->nullable()->change();
            $table->date('acto_fecha')->nullable()->change();
            $table->jsonb('metadata_manual')->nullable();
        });
        Schema::table('manual_cargo_versiones', function (Blueprint $table) {
            $table->string('source_id', 100)->nullable();
            $table->string('import_key', 64)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('natural_key', 64)->nullable()->index();
            $table->text('denominacion_fuente')->nullable();
            $table->text('dependencia')->nullable();
            $table->text('area_funcional')->nullable();
            $table->text('numero_cargos')->nullable();
            $table->text('jefe_inmediato')->nullable();
            $table->jsonb('metadata_manual')->nullable();
            $table->unique(['manual_funciones_version_id', 'import_key'], 'manual_ficha_import_unique');
            $table->unique(['manual_funciones_version_id', 'source_id'], 'manual_ficha_source_unique');
            // Composite FK below enforces assignment / profile / generic position agreement.
            $table->unique(['id', 'cargo_id'], 'manual_ficha_cargo_unique');
        });
        // Preserve existing PKs and every historical record. Legacy identities cannot collide
        // with imported source identities. Descriptive natural keys are intentionally nonunique.
        DB::statement("UPDATE manual_cargo_versiones SET import_key = md5('legacy:' || id::text) WHERE import_key IS NULL");
        Schema::table('manual_cargo_versiones', function (Blueprint $table) {
            $table->dropUnique('manual_version_cargo_unique');
        });
        Schema::table('manual_funciones_esenciales', function (Blueprint $table) {
            $table->integer('numero_fuente')->nullable();
            $table->text('grupo')->nullable();
        });
        Schema::table('funcionario_cargo', function (Blueprint $table) {
            $table->unsignedBigInteger('manual_cargo_version_id')->nullable();
            $table->boolean('es_prueba_manual')->default(false);
            $table->foreign(['manual_cargo_version_id', 'cargo_id'], 'asignacion_ficha_cargo_fk')
                ->references(['id', 'cargo_id'])->on('manual_cargo_versiones')->restrictOnDelete();
        });
        Schema::create('manual_funciones_comunes_nivel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_funciones_version_id')->constrained('manual_funciones_versiones')->restrictOnDelete();
            $table->string('nivel', 60);
            $table->unsignedSmallInteger('orden');
            $table->text('descripcion');
            $table->jsonb('metadata_manual')->nullable();
            $table->timestamps();
            $table->unique(['manual_funciones_version_id', 'nivel', 'orden'], 'manual_comun_orden_unique');
        });
        Schema::create('manual_importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_funciones_version_id')->nullable()->constrained('manual_funciones_versiones')->restrictOnDelete();
            $table->text('archivo_fuente');
            $table->string('sha256', 64);
            $table->unsignedInteger('numero_registros');
            $table->text('importador');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resultado', 30);
            $table->jsonb('detalle');
            $table->timestampTz('fecha_importacion');
        });
    }

    public function down(): void
    {
        // Reinstating UNIQUE(version,cargo) can destroy valid multiple-profile data.
        throw new RuntimeException('MANUAL_ROLLBACK_REQUIRES_REVIEW: use an incremental forward migration.');
    }
};
