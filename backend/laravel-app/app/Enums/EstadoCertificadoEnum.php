<?php

namespace App\Enums;

enum EstadoCertificadoEnum: string
{
    case Vigente    = 'vigente';
    case Descargado = 'descargado';
    case Anulado    = 'anulado';
    case Vencido    = 'vencido';

    public function label(): string
    {
        return match ($this) {
            self::Vigente    => 'Vigente',
            self::Descargado => 'Descargado',
            self::Anulado    => 'Anulado',
            self::Vencido    => 'Vencido',
        };
    }

    public function esVigenteParaValidacion(): bool
    {
        return in_array($this, [self::Vigente, self::Descargado], true);
    }
}
