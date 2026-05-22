<?php

namespace App\Enums;

enum EstadoCertificadoEnum: string
{
    case Vigente = 'vigente';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match($this) {
            self::Vigente => 'Vigente',
            self::Anulado => 'Anulado',
        };
    }
}
