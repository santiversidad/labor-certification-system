<?php

namespace App\Services;

use App\Models\ManualCargoVersion;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SeleccionarFichaManualService
{
    public function porUnidad(string $unidadId, int $cargoId, \Carbon\CarbonInterface $fecha, ?int $fichaId = null): ManualCargoVersion
    {
        $fichas = app(UnidadesManualService::class)->enUnidad($fecha, $unidadId)->where('cargo_id', $cargoId)->with('version')->get();
        if ($fichas->count() !== 1) {
            throw ValidationException::withMessages(['cargo_id' => $fichas->isEmpty()
                ? 'No hay un perfil aplicable para esta dependencia, cargo y fecha.'
                : 'Hay varios perfiles para esta dependencia, cargo y fecha. Se requiere revisión administrativa del Manual.']);
        }
        $ficha = $fichas->sole();
        if ($fichaId !== null && $fichaId !== $ficha->id) {
            throw ValidationException::withMessages(['manual_cargo_version_id' => 'La ficha no corresponde a la dependencia, cargo y fecha seleccionados.']);
        }

        return $ficha;
    }

    public function seleccionar(int $cargoId, ?int $fichaId): ManualCargoVersion
    {
        if ($fichaId) {
            $ficha = ManualCargoVersion::with('version')->find($fichaId);
            if (! $ficha || $ficha->cargo_id !== $cargoId
                || ! app(ResolverFuncionesFuncionarioService::class)->versionVigente($ficha->version, CarbonImmutable::now())) {
                throw ValidationException::withMessages(['manual_cargo_version_id' => 'MANUAL_FICHA_INCOMPATIBLE']);
            }

            return $ficha;
        }
        $fichas = app(ResolverFuncionesFuncionarioService::class)->compatibles($cargoId, CarbonImmutable::now())->get();
        if ($fichas->count() !== 1) {
            throw ValidationException::withMessages(['manual_cargo_version_id' => $fichas->isEmpty()
                ? 'MANUAL_FICHA_NO_ENCONTRADA' : 'MANUAL_FICHA_AMBIGUA']);
        }

        return $fichas->sole();
    }
}
