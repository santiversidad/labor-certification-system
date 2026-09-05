<?php

namespace Tests\Support;

use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;

/** Synthetic source used only by the preexisting workflow tests. */
final class ManualFixture
{
    public static function ficha(Cargo $cargo): ManualCargoVersion
    {
        $manual = ManualFuncion::firstOrCreate(['codigo' => 'TEST-FIXTURE'], ['nombre' => 'Manual sintético de pruebas']);
        $version = $manual->versiones()->firstOrCreate(['version' => 'TEST'], [
            'estado' => 'publicado', 'acto_tipo' => 'Prueba', 'acto_numero' => 'TEST',
            'acto_fecha' => '2020-01-01', 'vigencia_desde' => '2020-01-01',
        ]);
        $ficha = $version->cargos()->firstOrCreate(['cargo_id' => $cargo->id], [
            'area_funcional' => 'Área de pruebas', 'dependencia' => 'Dependencia de pruebas',
            'proposito_principal' => 'Validar el flujo de certificación.',
        ]);
        $ficha->funciones()->firstOrCreate(['orden' => 1], ['descripcion' => 'Función sintética de pruebas.']);

        return $ficha;
    }

    public static function vincular(Funcionario $funcionario): void
    {
        $ficha = self::ficha($funcionario->cargo);
        $asignacion = $funcionario->historialCargos()->whereNull('fecha_fin')->first();
        if ($asignacion) {
            $asignacion->update(['manual_cargo_version_id' => $ficha->id]);
        } else {
            FuncionarioCargo::create(['funcionario_id' => $funcionario->id, 'cargo_id' => $ficha->cargo_id,
                'manual_cargo_version_id' => $ficha->id, 'tipo_vinculacion' => 'planta',
                'naturaleza_cargo' => 'carrera_administrativa', 'fecha_inicio' => '2020-01-01']);
        }
    }
}
