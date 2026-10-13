<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_certificado', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20)->unique();
            $table->unsignedBigInteger('publicada_id')->nullable();
            $table->timestamps();
        });
        Schema::create('plantilla_assets', function (Blueprint $table) {
            $table->id();
            $table->string('posicion', 10);
            $table->string('sha256', 64);
            $table->string('ruta')->unique();
            $table->string('mime', 30);
            $table->unsignedInteger('ancho');
            $table->unsignedInteger('alto');
            $table->unsignedInteger('bytes');
            $table->string('procedencia', 250);
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('plantilla_versiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_certificado')->restrictOnDelete();
            $table->unsignedInteger('numero');
            $table->string('estado', 15)->default('borrador');
            $table->jsonb('contenido');
            $table->string('sha256', 64);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('editado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('publicado_at')->nullable();
            $table->timestamps();
            $table->unique(['plantilla_id', 'numero']);
        });
        Schema::table('plantillas_certificado', function (Blueprint $table) {
            $table->foreign('publicada_id')->references('id')->on('plantilla_versiones')->restrictOnDelete();
        });
        DB::statement("ALTER TABLE plantillas_certificado ADD CONSTRAINT template_type CHECK (tipo IN ('sencillo','funciones'))");
        DB::statement("ALTER TABLE plantilla_versiones ADD CONSTRAINT template_state CHECK (estado IN ('borrador','publicada','archivada'))");
        DB::statement("CREATE UNIQUE INDEX template_one_published ON plantilla_versiones (plantilla_id) WHERE estado = 'publicada'");
        DB::statement("CREATE UNIQUE INDEX template_one_draft ON plantilla_versiones (plantilla_id) WHERE estado = 'borrador'");
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION protect_certificate_template_version() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN RAISE EXCEPTION 'TEMPLATE_VERSION_IMMUTABLE'; END IF;
                IF OLD.estado IN ('publicada','archivada') AND (
                    (to_jsonb(OLD) - ARRAY['estado','updated_at']) IS DISTINCT FROM (to_jsonb(NEW) - ARRAY['estado','updated_at'])
                    OR NOT (NEW.estado = OLD.estado OR (OLD.estado = 'publicada' AND NEW.estado = 'archivada'))
                ) THEN RAISE EXCEPTION 'TEMPLATE_VERSION_IMMUTABLE'; END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER template_version_guard BEFORE UPDATE OR DELETE ON plantilla_versiones
            FOR EACH ROW EXECUTE FUNCTION protect_certificate_template_version();
            CREATE FUNCTION protect_certificate_template_asset() RETURNS trigger AS $$
            BEGIN RAISE EXCEPTION 'TEMPLATE_ASSET_IMMUTABLE'; END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER template_asset_guard BEFORE UPDATE OR DELETE ON plantilla_assets
            FOR EACH ROW EXECUTE FUNCTION protect_certificate_template_asset();
        SQL);
        foreach (['sencillo', 'funciones'] as $type) {
            DB::table('plantillas_certificado')->insert(['tipo' => $type, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('TEMPLATE_HISTORY_ROLLBACK_REQUIRES_REVIEW');
    }
};
