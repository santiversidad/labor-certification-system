<?php

namespace App\Enums;

enum EstadoPagoEnum: string
{
    case Pendiente = 'pendiente';
    case Aprobado  = 'aprobado';
    case Rechazado = 'rechazado';

    public function label(): string
    {
        return match($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobado  => 'Aprobado',
            self::Rechazado => 'Rechazado',
        };
    }
}
