<?php

namespace App\Enums;

enum NaturalezaCargoEnum: string
{
    case CarreraAdministrativa = 'carrera_administrativa';
    case LibreNombramiento     = 'libre_nombramiento';
    case Provisional           = 'provisional';
    case Encargo               = 'encargo';

    public function label(): string
    {
        return match ($this) {
            self::CarreraAdministrativa => 'Carrera administrativa',
            self::LibreNombramiento     => 'Libre nombramiento y remocion',
            self::Provisional           => 'Provisional',
            self::Encargo               => 'Encargo',
        };
    }
}
