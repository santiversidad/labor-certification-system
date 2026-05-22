<?php

namespace App\Enums;

enum RoleEnum: string
{
    case Admin       = 'admin';
    case Secretario  = 'secretario';
    case Funcionario = 'funcionario';

    public function label(): string
    {
        return match($this) {
            self::Admin       => 'Administrador',
            self::Secretario  => 'Secretario',
            self::Funcionario => 'Funcionario',
        };
    }
}
