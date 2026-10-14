<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escala_salarial_vigencias', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio')->unique();
            $table->string('estado', 12)->default('borrador');
            $table->decimal('porcentaje_referencia', 5, 2)->nullable();
            $table->foreignId('vigencia_base_id')->nullable()->constrained('escala_salarial_vigencias')->restrictOnDelete();
            $table->string('acto_administrativo', 250)->nullable();
            $table->date('fecha_vigencia')->nullable();
            $table->string('fuente', 250);
            $table->text('observaciones')->nullable();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('publicado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('publicado_at')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('escala_salarial_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vigencia_id')->constrained('escala_salarial_vigencias')->restrictOnDelete();
            $table->foreignId('cargo_id')->nullable()->constrained('cargos')->restrictOnDelete();
            $table->string('codigo', 10);
            $table->string('grado', 5);
            $table->string('denominacion', 200);
            $table->unsignedInteger('numero_cargos')->nullable();
            $table->decimal('asignacion_basica_mensual', 12, 0)->nullable();
            $table->string('origen_valor', 40)->default('pendiente');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['vigencia_id', 'codigo', 'grado', 'denominacion'], 'escala_item_source_unique');
            $table->index(['codigo', 'grado']);
        });
        DB::statement("ALTER TABLE escala_salarial_vigencias ADD CONSTRAINT escala_estado_check CHECK (estado IN ('borrador','vigente','archivada')), ADD CONSTRAINT escala_anio_check CHECK (anio BETWEEN 1900 AND 2199), ADD CONSTRAINT escala_porcentaje_check CHECK (porcentaje_referencia BETWEEN 0 AND 100)");
        DB::statement("ALTER TABLE escala_salarial_items ADD CONSTRAINT escala_valor_check CHECK (asignacion_basica_mensual > 0), ADD CONSTRAINT escala_numero_check CHECK (numero_cargos >= 0), ADD CONSTRAINT escala_origen_check CHECK (origen_valor IN ('pendiente','manual','importado','calculado_por_porcentaje'))");
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION protect_salary_scale_items() RETURNS trigger AS $$
            DECLARE year_id bigint; year_state text;
            BEGIN
                IF TG_OP = 'DELETE' THEN year_id := OLD.vigencia_id; ELSE year_id := NEW.vigencia_id; END IF;
                SELECT estado INTO year_state FROM escala_salarial_vigencias WHERE id = year_id FOR UPDATE;
                IF year_state IS DISTINCT FROM 'borrador' THEN
                    RAISE EXCEPTION 'SALARY_SCALE_READ_ONLY';
                END IF;
                IF TG_OP = 'UPDATE' AND NEW.vigencia_id IS DISTINCT FROM OLD.vigencia_id THEN
                    RAISE EXCEPTION 'SALARY_SCALE_ITEM_YEAR_IMMUTABLE';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER salary_scale_items_guard BEFORE INSERT OR UPDATE OR DELETE ON escala_salarial_items
            FOR EACH ROW EXECUTE FUNCTION protect_salary_scale_items();

            CREATE FUNCTION protect_salary_scale_year() RETURNS trigger AS $$
            BEGIN
                IF OLD.estado <> 'borrador' AND (
                    TG_OP = 'DELETE' OR NEW.estado = 'borrador' OR
                    NEW.anio IS DISTINCT FROM OLD.anio OR
                    NEW.vigencia_base_id IS DISTINCT FROM OLD.vigencia_base_id OR
                    NEW.porcentaje_referencia IS DISTINCT FROM OLD.porcentaje_referencia
                ) THEN RAISE EXCEPTION 'SALARY_SCALE_READ_ONLY'; END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER salary_scale_year_guard BEFORE UPDATE OR DELETE ON escala_salarial_vigencias
            FOR EACH ROW EXECUTE FUNCTION protect_salary_scale_year();
            SQL);
        DB::table('permissions')->updateOrInsert(['name' => 'escala_salarial.gestionar', 'guard_name' => 'web'], ['created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('permissions')->where('name', 'escala_salarial.gestionar')->where('guard_name', 'web')->value('id');
        $admin = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        if ($admin) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $id, 'role_id' => $admin]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('La escala salarial histórica requiere respaldo y revisión explícita; no se elimina automáticamente.');
    }
};
