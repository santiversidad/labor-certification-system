<?php

namespace App\Services;

use App\Models\ManualCargoVersion;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SeleccionarFichaManualService
{
    public function seleccionar(int $cargoId, ?int $fichaId): ManualCargoVersion
    {
        if ($fichaId) {
            $ficha = ManualCargoVersion::with('version')->find($fichaId);
            if (! $ficha || $ficha->cargo_id !== $cargoId || ! in_array($ficha->version->estado, ['borrador', 'publicado'], true)) {
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
