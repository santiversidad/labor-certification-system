<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_funciones_versiones', function (Blueprint $table) {
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
        });

        Schema::create('manual_cargo_lineages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predecessor_id')->constrained('manual_cargo_versiones')->restrictOnDelete();
            $table->foreignId('successor_id')->constrained('manual_cargo_versiones')->restrictOnDelete();
            $table->string('clasificacion', 30);
            $table->string('estado', 30)->default('propuesto');
            $table->decimal('puntaje', 5, 2)->nullable();
            $table->jsonb('diferencias')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['predecessor_id', 'successor_id'], 'manual_lineage_pair_unique');
        });

        Schema::create('funcionario_cargo_manual_fichas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funcionario_cargo_id')->constrained('funcionario_cargo')->restrictOnDelete();
            $table->foreignId('manual_cargo_version_id')->constrained('manual_cargo_versiones')->restrictOnDelete();
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->string('origen', 40)->default('asignacion_inicial');
            $table->foreignId('lineage_id')->nullable()->constrained('manual_cargo_lineages')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['funcionario_cargo_id', 'vigencia_desde', 'vigencia_hasta'], 'funcionario_ficha_vigencia_index');
        });

        Schema::create('manual_actualizaciones_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_version_id')->constrained('manual_funciones_versiones')->restrictOnDelete();
            $table->foreignId('to_version_id')->constrained('manual_funciones_versiones')->restrictOnDelete();
            $table->foreignId('funcionario_cargo_id')->constrained('funcionario_cargo')->restrictOnDelete();
            $table->foreignId('ficha_anterior_id')->constrained('manual_cargo_versiones')->restrictOnDelete();
            $table->foreignId('ficha_candidata_id')->nullable()->constrained('manual_cargo_versiones')->restrictOnDelete();
            $table->string('clasificacion', 30);
            $table->jsonb('motivos')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['to_version_id', 'funcionario_cargo_id'], 'manual_actualizacion_asignacion_unique');
        });

        DB::statement('ALTER TABLE funcionario_cargo_manual_fichas ADD CONSTRAINT funcionario_ficha_fechas_check CHECK (vigencia_hasta IS NULL OR vigencia_desde IS NULL OR vigencia_hasta >= vigencia_desde)');
        DB::statement("ALTER TABLE manual_cargo_lineages ADD CONSTRAINT manual_lineage_clasificacion_check CHECK (clasificacion IN ('SIN_CAMBIOS','MODIFICADA'))");
        DB::statement("ALTER TABLE manual_cargo_lineages ADD CONSTRAINT manual_lineage_estado_check CHECK (estado IN ('propuesto','confirmado','descartado'))");
        DB::statement("ALTER TABLE manual_actualizaciones_asignaciones ADD CONSTRAINT manual_actualizacion_clasificacion_check CHECK (clasificacion IN ('AUTO_MIGRABLE','REQUIERE_REVISION','SIN_EQUIVALENTE'))");

        DB::statement(<<<'SQL'
            INSERT INTO funcionario_cargo_manual_fichas
                (funcionario_cargo_id, manual_cargo_version_id, vigencia_desde, vigencia_hasta, origen, created_at, updated_at)
            SELECT fc.id, fc.manual_cargo_version_id, fc.fecha_inicio, fc.fecha_fin, 'asignacion_inicial', NOW(), NOW()
            FROM funcionario_cargo fc
            WHERE fc.manual_cargo_version_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM funcionario_cargo_manual_fichas fm
                  WHERE fm.funcionario_cargo_id = fc.id
              )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX manual_version_actual_unique
            ON manual_funciones_versiones (manual_funciones_id)
            WHERE estado = 'publicado' AND vigencia_hasta IS NULL
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_published_manual_profile_mutation() RETURNS trigger AS $$
            DECLARE version_state text; version_id bigint;
            BEGIN
                version_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.manual_funciones_version_id ELSE NEW.manual_funciones_version_id END;
                SELECT estado INTO version_state FROM manual_funciones_versiones
                WHERE id = version_id;
                IF version_state IN ('publicado', 'inactivo') THEN
                    RAISE EXCEPTION 'MANUAL_VERSION_IMMUTABLE';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER manual_profile_immutable
            BEFORE INSERT OR UPDATE OR DELETE ON manual_cargo_versiones
            FOR EACH ROW EXECUTE FUNCTION prevent_published_manual_profile_mutation();

            CREATE OR REPLACE FUNCTION prevent_published_manual_function_mutation() RETURNS trigger AS $$
            DECLARE version_state text; profile_id bigint;
            BEGIN
                profile_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.manual_cargo_version_id ELSE NEW.manual_cargo_version_id END;
                SELECT v.estado INTO version_state
                FROM manual_cargo_versiones c
                JOIN manual_funciones_versiones v ON v.id = c.manual_funciones_version_id
                WHERE c.id = profile_id;
                IF version_state IN ('publicado', 'inactivo') THEN
                    RAISE EXCEPTION 'MANUAL_VERSION_IMMUTABLE';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER manual_function_immutable
            BEFORE INSERT OR UPDATE OR DELETE ON manual_funciones_esenciales
            FOR EACH ROW EXECUTE FUNCTION prevent_published_manual_function_mutation();

            CREATE OR REPLACE FUNCTION prevent_published_manual_cargo_catalog_mutation() RETURNS trigger AS $$
            BEGIN
                IF (OLD.codigo, OLD.grado, OLD.denominacion, OLD.nivel)
                    IS DISTINCT FROM (NEW.codigo, NEW.grado, NEW.denominacion, NEW.nivel)
                    AND EXISTS (
                        SELECT 1 FROM manual_cargo_versiones c
                        JOIN manual_funciones_versiones v ON v.id = c.manual_funciones_version_id
                        WHERE c.cargo_id = OLD.id AND v.estado IN ('publicado', 'inactivo')
                    ) THEN
                    RAISE EXCEPTION 'MANUAL_VERSION_IMMUTABLE';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER manual_cargo_catalog_immutable
            BEFORE UPDATE ON cargos
            FOR EACH ROW EXECUTE FUNCTION prevent_published_manual_cargo_catalog_mutation();
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('MANUAL_VERSIONING_ROLLBACK_REQUIRES_REVIEW: preserve lineage and historical assignments.');
    }
};
