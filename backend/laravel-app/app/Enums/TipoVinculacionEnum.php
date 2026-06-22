<?php

namespace App\Enums;

enum TipoVinculacionEnum: string
{
    case Planta      = 'planta';
    case Provisional = 'provisional';
    case Encargo     = 'encargo';
    case Temporal    = 'temporal';

    public function label(): string
    {
        return match ($this) {
            self::Planta      => 'Planta',
            self::Provisional => 'Provisional',
            self::Encargo     => 'Encargo',
            self::Temporal    => 'Temporal',
        };
    }
}
