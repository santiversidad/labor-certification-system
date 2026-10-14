<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->date('periodo_mes')->nullable()->after('requiere_salario');
        });

        DB::statement(<<<'SQL'
            UPDATE solicitudes_certificacion
            -- Los registros previos fueron escritos cuando Laravel usaba UTC.
            -- Se convierten a la zona de negocio antes de determinar el mes.
            SET periodo_mes = date_trunc(
                'month',
                created_at AT TIME ZONE 'UTC' AT TIME ZONE 'America/Bogota'
            )::date
            WHERE periodo_mes IS NULL
        SQL);

        $duplicados = DB::table('solicitudes_certificacion')
            ->select('funcionario_id', 'periodo_mes', 'requiere_salario', DB::raw('COUNT(*) AS total'))
            ->groupBy('funcionario_id', 'periodo_mes', 'requiere_salario')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicados) {
            throw new RuntimeException(
                'Existen solicitudes duplicadas por funcionario, periodo y modalidad. '
                .'La migracion se cancela sin eliminar registros.'
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes_certificacion
            ADD CONSTRAINT solicitudes_periodo_mes_primer_dia_check
            CHECK (EXTRACT(DAY FROM periodo_mes) = 1)
        SQL);
        DB::statement('ALTER TABLE solicitudes_certificacion ALTER COLUMN periodo_mes SET NOT NULL');

        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->unique(
                ['funcionario_id', 'periodo_mes', 'requiere_salario'],
                'solicitudes_funcionario_periodo_modalidad_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropUnique('solicitudes_funcionario_periodo_modalidad_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes_certificacion
            DROP CONSTRAINT IF EXISTS solicitudes_periodo_mes_primer_dia_check
        SQL);

        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropColumn('periodo_mes');
        });
    }
};
