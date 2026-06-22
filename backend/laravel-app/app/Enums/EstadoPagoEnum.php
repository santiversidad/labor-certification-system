<?php

namespace App\Enums;

enum EstadoPagoEnum: string
{
    case NoRequerido = 'no_requerido';
    case Pendiente   = 'pendiente';
    case Cargado     = 'cargado';
    case Aprobado    = 'aprobado';
    case Rechazado   = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::NoRequerido => 'No requerido',
            self::Pendiente   => 'Pendiente',
            self::Cargado     => 'Cargado',
            self::Aprobado    => 'Aprobado',
            self::Rechazado   => 'Rechazado',
        };
    }
}
