<?php

namespace App\Enums;

enum TipoCertificadoEnum: string
{
    case Sencillo = 'sencillo';
    case Funciones = 'funciones';

    public function label(): string
    {
        return match ($this) {
            self::Sencillo => 'Certificado Laboral Sencillo',
            self::Funciones => 'Certificado Laboral con Funciones',
        };
    }
}
