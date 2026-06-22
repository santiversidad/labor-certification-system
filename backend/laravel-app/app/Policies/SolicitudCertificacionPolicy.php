<?php

namespace App\Policies;

use App\Enums\EstadoSolicitudEnum;
use App\Models\SolicitudCertificacion;
use App\Models\User;

class SolicitudCertificacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('solicitudes.ver');
    }

    public function view(User $user, SolicitudCertificacion $solicitud): bool
    {
        if ($user->can('solicitudes.ver') && ! $user->hasRole('funcionario')) {
            return true;
        }

        return $user->can('solicitudes.ver')
            && $user->hasRole('funcionario')
            && $user->funcionario?->id === $solicitud->funcionario_id;
    }

    public function create(User $user): bool
    {
        return $user->can('solicitudes.crear') && $user->hasRole('funcionario');
    }

    public function cambiarEstado(User $user, SolicitudCertificacion $solicitud): bool
    {
        if (! $user->can('solicitudes.cambiar_estado')) {
            return false;
        }

        return ! in_array($solicitud->estado, [
            EstadoSolicitudEnum::Rechazada,
            EstadoSolicitudEnum::CertificadoGenerado,
            EstadoSolicitudEnum::Cerrada,
        ], true);
    }

    public function aprobar(User $user, SolicitudCertificacion $solicitud): bool
    {
        return $user->can('solicitudes.aprobar') && $this->cambiarEstado($user, $solicitud);
    }

    public function rechazar(User $user, SolicitudCertificacion $solicitud): bool
    {
        return $user->can('solicitudes.rechazar') && $this->cambiarEstado($user, $solicitud);
    }

    public function generarCertificado(User $user, SolicitudCertificacion $solicitud): bool
    {
        return $user->can('certificados.generar') && ! $user->hasRole('funcionario');
    }
}
