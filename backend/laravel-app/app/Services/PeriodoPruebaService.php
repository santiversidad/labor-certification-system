<?php

namespace App\Services;

use App\Enums\NaturalezaCargoEnum;
use App\Models\Funcionario;
use Carbon\CarbonInterface;

class PeriodoPruebaService
{
    public function estaEnPeriodoPrueba(Funcionario $funcionario, ?CarbonInterface $fechaReferencia = null): bool
    {
        $fechaReferencia ??= now();
        $funcionario->loadMissing('cargoBase');
        $cargoBase = $funcionario->cargoBase;

        if (! $cargoBase || $cargoBase->naturaleza_cargo !== NaturalezaCargoEnum::CarreraAdministrativa) {
            return false;
        }

        return $cargoBase->fecha_inicio !== null
            && $cargoBase->fecha_inicio->copy()->addMonthsNoOverflow(6)->greaterThanOrEqualTo($fechaReferencia);
    }
}
