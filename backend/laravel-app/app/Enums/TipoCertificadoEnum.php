<?php

namespace App\Enums;

enum TipoCertificadoEnum: string
{
    case Sencillo = 'sencillo';
    case Funciones = 'funciones';

    public function label(): string
    {
        return match ($this) {
            self::Sencillo => 'Certificado laboral sencillo',
            self::Funciones => 'Certificado laboral con funciones',
        };
    }
}
