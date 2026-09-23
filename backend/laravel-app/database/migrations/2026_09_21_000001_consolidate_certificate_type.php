<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('solicitudes_certificacion')->where('requiere_salario', true)->exists()) {
            throw new RuntimeException(
                'La migración no convierte solicitudes salariales. Retire o preserve esos datos mediante un procedimiento auditado antes de continuar.'
            );
        }

        if (DB::table('solicitudes_certificacion')
            ->whereNotIn('tipo_certificado', ['laboral', 'sencillo', 'funciones'])
            ->exists()) {
            throw new RuntimeException('Existen tipos históricos que no pueden convertirse de forma inequívoca.');
        }

        if (DB::table('certificados')->whereRaw("snapshot_datos::text ~* 'salario|salary'")->exists()) {
            throw new RuntimeException('Existen snapshots incompatibles. No se alteran ni eliminan automáticamente.');
        }

        if (DB::table('funcionario_cargo')->whereNotNull('salario_override')->exists()) {
            throw new RuntimeException('Existen referencias salariales en asignaciones. La migración se cancela sin eliminarlas.');
        }

        if (DB::table('rangos_salariales')->exists()) {
            throw new RuntimeException('Existen rangos salariales. La migración se cancela sin eliminarlos.');
        }

        DB::table('solicitudes_certificacion')
            ->where('tipo_certificado', 'laboral')
            ->update(['tipo_certificado' => 'sencillo']);

        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->dropUnique('solicitudes_funcionario_periodo_modalidad_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE solicitudes_certificacion
            ADD CONSTRAINT solicitudes_tipo_certificado_check
            CHECK (tipo_certificado IN ('sencillo', 'funciones'))
        SQL);

        Schema::table('solicitudes_certificacion', function (Blueprint $table) {
            $table->unique(
                ['funcionario_id', 'periodo_mes', 'tipo_certificado'],
                'solicitudes_funcionario_periodo_tipo_unique'
            );
            $table->dropColumn('requiere_salario');
        });

        Schema::table('funcionario_cargo', function (Blueprint $table) {
            $table->dropColumn('salario_override');
        });

        Schema::drop('rangos_salariales');

        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'rangos_salariales.ver',
                'rangos_salariales.crear',
                'rangos_salariales.editar',
            ])
            ->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        throw new RuntimeException('El retiro del modelo salarial es deliberadamente irreversible. Restaure el respaldo previo si necesita auditar el estado anterior.');
    }
};
