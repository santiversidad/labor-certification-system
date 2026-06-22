<?php

namespace App\Policies;

use App\Models\PagoSoporte;
use App\Models\User;

class PagoSoportePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pagos.ver') && ! $user->hasRole('funcionario');
    }

    public function view(User $user, PagoSoporte $pago): bool
    {
        if ($user->can('pagos.ver') && ! $user->hasRole('funcionario')) {
            return true;
        }

        return $user->can('pagos.cargar')
            && $user->funcionario?->id === $pago->funcionario_id;
    }

    public function validar(User $user, PagoSoporte $pago): bool
    {
        return $user->can('pagos.validar') && ! $user->hasRole('funcionario');
    }

    public function rechazar(User $user, PagoSoporte $pago): bool
    {
        return $user->can('pagos.rechazar') && ! $user->hasRole('funcionario');
    }
}
