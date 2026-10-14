<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $usuariosDuplicados = DB::table('funcionarios')
            ->select('user_id')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($usuariosDuplicados) {
            throw new RuntimeException('No se puede crear UNIQUE funcionarios.user_id: existen duplicados.');
        }

        $pagosDuplicados = DB::table('pagos_soportes')
            ->select('solicitud_certificacion_id')
            ->groupBy('solicitud_certificacion_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($pagosDuplicados) {
            throw new RuntimeException('No se puede crear UNIQUE pagos_soportes.solicitud_certificacion_id: existen duplicados.');
        }

        DB::statement('CREATE UNIQUE INDEX funcionarios_user_id_unique ON funcionarios (user_id)');
        DB::statement('CREATE UNIQUE INDEX pagos_soportes_solicitud_unique ON pagos_soportes (solicitud_certificacion_id)');
        DB::statement(<<<'SQL'
            ALTER TABLE manual_funciones_esenciales
            ADD CONSTRAINT manual_funcion_orden_positivo_check CHECK (orden > 0)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE certificados
            ADD CONSTRAINT certificados_snapshot_completo_check CHECK (
                (snapshot_schema_version IS NULL AND snapshot_datos IS NULL)
                OR (snapshot_schema_version IS NOT NULL AND snapshot_datos IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE certificados DROP CONSTRAINT IF EXISTS certificados_snapshot_completo_check');
        DB::statement('ALTER TABLE manual_funciones_esenciales DROP CONSTRAINT IF EXISTS manual_funcion_orden_positivo_check');
        DB::statement('DROP INDEX IF EXISTS pagos_soportes_solicitud_unique');
        DB::statement('DROP INDEX IF EXISTS funcionarios_user_id_unique');
    }
};
