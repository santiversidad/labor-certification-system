<?php

namespace App\Services;

use App\Models\ManualCargoVersion;
use Carbon\CarbonInterface;
use DomainException;

/** @deprecated Legacy administrative query. Certificate generation must use ResolverFuncionesFuncionarioService. */
class ResolverFuncionesCargoService
{
    public function resolver(int $cargoId, CarbonInterface $fecha): ?array
    {
        $coincidencias = ManualCargoVersion::query()
            ->with(['version.manual', 'funciones'])
            ->where('cargo_id', $cargoId)
            ->whereHas('version', fn ($query) => $query
                ->where('estado', 'publicado')
                ->whereDate('vigencia_desde', '<=', $fecha->toDateString())
                ->where(fn ($vigencia) => $vigencia
                    ->whereNull('vigencia_hasta')
                    ->orWhereDate('vigencia_hasta', '>=', $fecha->toDateString())))
            ->get();

        if ($coincidencias->count() > 1) {
            throw new DomainException('Existen varias versiones vigentes del Manual de Funciones para el cargo y la fecha indicados.');
        }

        $cargoVersion = $coincidencias->first();
        if (! $cargoVersion) {
            return null;
        }

        return [
            'manual_id' => $cargoVersion->version->manual->id,
            'manual_codigo' => $cargoVersion->version->manual->codigo,
            'manual_nombre' => $cargoVersion->version->manual->nombre,
            'version_id' => $cargoVersion->version->id,
            'version' => $cargoVersion->version->version,
            'vigencia_desde' => $cargoVersion->version->vigencia_desde->toDateString(),
            'vigencia_hasta' => $cargoVersion->version->vigencia_hasta?->toDateString(),
            'acto' => [
                'tipo' => $cargoVersion->version->acto_tipo,
                'numero' => $cargoVersion->version->acto_numero,
                'fecha' => $cargoVersion->version->acto_fecha?->toDateString(),
                'referencia' => $cargoVersion->version->acto_referencia,
            ],
            'proposito_principal' => $cargoVersion->proposito_principal,
            'requisitos' => $cargoVersion->requisitos,
            'funciones' => $cargoVersion->funciones->map(fn ($funcion) => [
                'orden' => $funcion->orden,
                'descripcion' => $funcion->descripcion,
            ])->values()->all(),
        ];
    }
}
