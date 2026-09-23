<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SALARY_PERMISSIONS = [
        'rangos_salariales.ver',
        'rangos_salariales.crear',
        'rangos_salariales.editar',
    ];

    public function up(): void
    {
        $unsupported = DB::table('solicitudes_certificacion')
            ->whereNotIn('tipo_certificado', ['laboral', 'funciones'])
            ->distinct()
            ->orderBy('tipo_certificado')
            ->pluck('tipo_certificado');

        if ($unsupported->isNotEmpty()) {
            throw new RuntimeException(
                'TIPOS_CERTIFICADO_NO_MIGRABLES: '.$unsupported->implode(', ')
            );
        }

        $duplicates = DB::table('solicitudes_certificacion')
            ->selectRaw("funcionario_id, periodo_mes, CASE WHEN tipo_certificado = 'laboral' THEN 'sencillo' ELSE tipo_certificado END AS tipo_normalizado, COUNT(*) AS total")
            ->groupByRaw("funcionario_id, periodo_mes, CASE WHEN tipo_certificado = 'laboral' THEN 'sencillo' ELSE tipo_certificado END")
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Existen solicitudes duplicadas por funcionario, periodo y tipo canónico. '
                .'La migración se cancela sin eliminar registros.'
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes_certificacion
            DROP CONSTRAINT IF EXISTS solicitudes_funcionario_periodo_modalidad_unique
        SQL);

        DB::table('solicitudes_certificacion')
            ->where('tipo_certificado', 'laboral')
            ->update(['tipo_certificado' => 'sencillo']);

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes_certificacion
            ADD CONSTRAINT solicitudes_tipo_certificado_check
            CHECK (tipo_certificado IN ('sencillo', 'funciones'))
        SQL);

        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropColumn('requiere_salario');
            $table->unique(
                ['funcionario_id', 'periodo_mes', 'tipo_certificado'],
                'solicitudes_funcionario_periodo_tipo_unique'
            );
        });

        Schema::table('funcionario_cargo', function (Blueprint $table) {
            $table->dropColumn('salario_override');
        });

        Schema::dropIfExists('rangos_salariales');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', self::SALARY_PERMISSIONS)->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migración no reversible automáticamente: restaurar el backup previo es la única forma '
            .'de recuperar datos salariales sin inventarlos ni inferirlos desde tipo_certificado.'
        );
    }
};
