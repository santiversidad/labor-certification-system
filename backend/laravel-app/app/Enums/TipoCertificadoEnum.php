<?php

namespace App\Enums;

enum TipoCertificadoEnum: string
{
    case Laboral        = 'laboral';
    case Funciones      = 'funciones';
    case Salario        = 'salario';
    case LaboralSalario = 'laboral_salario';

    public function label(): string
    {
        return match($this) {
            self::Laboral        => 'Certificado Laboral',
            self::Funciones      => 'Certificado de Funciones',
            self::Salario        => 'Certificado de Salario',
            self::LaboralSalario => 'Certificado Laboral con Salario',
        };
    }
}
