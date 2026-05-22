<?php

namespace App\Enums;

enum EstadoFuncionarioEnum: string
{
    case Activo    = 'activo';
    case Retirado  = 'retirado';
    case Suspendido = 'suspendido';

    public function label(): string
    {
        return match($this) {
            self::Activo     => 'Activo',
            self::Retirado   => 'Retirado',
            self::Suspendido => 'Suspendido',
        };
    }
}
