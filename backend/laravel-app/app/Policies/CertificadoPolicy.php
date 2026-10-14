<?php

namespace App\Policies;

use App\Models\Certificado;
use App\Models\User;

class CertificadoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('certificados.ver') && ! $user->hasRole('funcionario');
    }

    public function view(User $user, Certificado $certificado): bool
    {
        return $user->can('certificados.ver') && ! $user->hasRole('funcionario');
    }

    public function descargar(User $user, Certificado $certificado): bool
    {
        return $user->can('certificados.descargar') && ! $user->hasRole('funcionario');
    }

    public function anular(User $user, Certificado $certificado): bool
    {
        return $user->can('certificados.anular') && ! $user->hasRole('funcionario');
    }
}
