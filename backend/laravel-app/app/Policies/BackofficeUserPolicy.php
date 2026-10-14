<?php

namespace App\Policies;

use App\Models\User;

class BackofficeUserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isBackofficeAccount() && $actor->hasRole('admin') && $actor->can('usuarios.ver');
    }

    public function create(User $actor): bool
    {
        return $actor->isBackofficeAccount() && $actor->hasRole('admin') && $actor->can('usuarios.crear');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isBackofficeAccount() && $actor->hasRole('admin') && $actor->can('usuarios.editar')
            && $target->tipo_cuenta === 'backoffice';
    }
}
