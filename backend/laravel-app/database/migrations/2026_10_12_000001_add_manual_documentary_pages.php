<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_cargo_versiones', function (Blueprint $table) {
            // Original extraction stays immutable in metadata_manual/content_hash.
            $table->unsignedInteger('pagina_inicio')->nullable();
            $table->jsonb('verificacion_documental')->nullable();
            $table->unique(['manual_funciones_version_id', 'pagina_inicio'], 'manual_pagina_version_unique');
        });
        DB::statement('ALTER TABLE manual_cargo_versiones ADD CONSTRAINT manual_pagina_positiva CHECK (pagina_inicio IS NULL OR pagina_inicio > 0)');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_published_manual_profile_mutation() RETURNS trigger AS $$
            DECLARE version_state text; version_id bigint; old_state text;
            BEGIN
                version_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.manual_funciones_version_id ELSE NEW.manual_funciones_version_id END;
                SELECT estado INTO version_state FROM manual_funciones_versiones WHERE id = version_id;
                IF TG_OP = 'UPDATE' THEN
                    SELECT estado INTO old_state FROM manual_funciones_versiones WHERE id = OLD.manual_funciones_version_id;
                    IF (version_state IN ('publicado', 'inactivo') OR old_state IN ('publicado', 'inactivo')) THEN
                        IF (to_jsonb(OLD) - ARRAY['pagina_inicio', 'verificacion_documental', 'updated_at'])
                            IS DISTINCT FROM (to_jsonb(NEW) - ARRAY['pagina_inicio', 'verificacion_documental', 'updated_at']) THEN
                            RAISE EXCEPTION 'MANUAL_VERSION_IMMUTABLE';
                        END IF;
                        RETURN NEW;
                    END IF;
                ELSIF version_state IN ('publicado', 'inactivo') THEN
                    RAISE EXCEPTION 'MANUAL_VERSION_IMMUTABLE';
                END IF;
                IF TG_OP = 'DELETE' THEN RETURN OLD; ELSE RETURN NEW; END IF;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('MANUAL_DOCUMENTARY_ROLLBACK_REQUIRES_REVIEW');
    }
};
