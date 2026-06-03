<?php

namespace App\Policies;

use App\Enums\EstadoSolicitudEnum;
use App\Models\Funcionario;
use App\Models\SolicitudCertificacion;
use App\Models\User;

class SolicitudCertificacionPolicy
{
    /**
     * Admin y secretario ven todas.
     * Funcionario solo ve las propias.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('solicitudes.ver');
    }

    /**
     * Admin y secretario ven cualquiera.
     * Funcionario solo ve la suya.
     */
    public function view(User $user, SolicitudCertificacion $solicitud): bool
    {
        if (! $user->can('solicitudes.ver')) {
            return false;
        }

        if ($user->hasRole('funcionario')) {
            return $user->funcionario?->id === $solicitud->funcionario_id;
        }

        return true;
    }

    /**
     * Solo funcionarios pueden crear solicitudes (para sí mismos).
     */
    public function create(User $user): bool
    {
        return $user->can('solicitudes.crear') && $user->hasRole('funcionario');
    }

    /**
     * Solo admin y secretario pueden cambiar el estado.
     */
    public function cambiarEstado(User $user, SolicitudCertificacion $solicitud): bool
    {
        if (! $user->can('solicitudes.cambiar_estado')) {
            return false;
        }

        // No se puede cambiar estado si ya fue generado o cancelado
        return ! in_array($solicitud->estado, [
            EstadoSolicitudEnum::Generado,
            EstadoSolicitudEnum::Cancelado,
        ]);
    }

    /**
     * Un funcionario puede cancelar su propia solicitud si aún está pendiente.
     */
    public function cancelar(User $user, SolicitudCertificacion $solicitud): bool
    {
        if (! $user->hasRole('funcionario')) {
            return false;
        }

        if ($user->funcionario?->id !== $solicitud->funcionario_id) {
            return false;
        }

        return $solicitud->estado === EstadoSolicitudEnum::Pendiente;
    }
}
