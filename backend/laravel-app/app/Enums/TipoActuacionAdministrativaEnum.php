<?php

namespace App\Enums;

enum TipoActuacionAdministrativaEnum: string
{
    case Nombramiento  = 'nombramiento';
    case Posesion      = 'posesion';
    case Encargo       = 'encargo';
    case Terminacion   = 'terminacion';
    case Traslado      = 'traslado';
    case PeriodoPrueba = 'periodo_prueba';
    case Otra          = 'otra';

    public function label(): string
    {
        return match ($this) {
            self::Nombramiento  => 'Nombramiento',
            self::Posesion      => 'Posesion',
            self::Encargo       => 'Encargo',
            self::Terminacion   => 'Terminacion',
            self::Traslado      => 'Traslado',
            self::PeriodoPrueba => 'Periodo de prueba',
            self::Otra          => 'Otra',
        };
    }
}
