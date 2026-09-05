<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Models\RangoSalarial;
use Carbon\CarbonInterface;
use DomainException;

class ResolverSalarioFuncionarioService
{
    public function resolver(Funcionario $funcionario, CarbonInterface $fechaReferencia): array
    {
        $asignaciones = $funcionario->historialCargos()
            ->with('cargo')
            ->whereDate('fecha_inicio', '<=', $fechaReferencia->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('fecha_fin')
                ->orWhereDate('fecha_fin', '>=', $fechaReferencia->toDateString()))
            ->get();

        if ($asignaciones->count() > 1) {
            throw new DomainException('ASIGNACION_AMBIGUA');
        }

        if ($asignaciones->isEmpty()) {
            throw new DomainException('ASIGNACION_NO_VIGENTE');
        }

        $asignacion = $asignaciones->first();
        $cargo = $asignacion->cargo;
        if (! $cargo) {
            throw new DomainException('CARGO_INVALIDO');
        }

        if ($asignacion?->salario_override !== null) {
            return [
                'valor' => (string) $asignacion->salario_override,
                'moneda' => 'COP',
                'fuente' => 'funcionario_cargo.salario_override',
                'fecha_referencia' => $fechaReferencia->toDateString(),
                'cargo_id' => $cargo->id,
            ];
        }

        $rangos = RangoSalarial::query()
            ->where('codigo', $cargo->codigo)
            ->where('grado', $cargo->grado)
            ->where('vigencia_anio', $fechaReferencia->year)
            ->where('estado', true)
            ->get();

        if ($rangos->isEmpty()) {
            throw new DomainException('SALARIO_NO_RESOLUBLE');
        }

        if ($rangos->count() > 1) {
            throw new DomainException('SALARIO_AMBIGUO');
        }

        $rango = $rangos->first();

        return [
            'valor' => (string) $rango->salario_basico,
            'moneda' => $rango->moneda,
            'fuente' => 'rangos_salariales',
            'rango_salarial_id' => $rango->id,
            'vigencia_anio' => $rango->vigencia_anio,
            'fecha_referencia' => $fechaReferencia->toDateString(),
            'cargo_id' => $cargo->id,
        ];
    }
}
