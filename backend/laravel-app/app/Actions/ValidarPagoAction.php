<?php

namespace App\Actions;

use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\PagoSoporte;
use App\Models\User;

class ValidarPagoAction
{
    public function aprobar(PagoSoporte $pago, User $validador, ?string $observaciones): void
    {
        // Actualizar estado del soporte
        $pago->update([
            'estado'        => EstadoPagoEnum::Aprobado,
            'observaciones' => $observaciones,
            'validado_por'  => $validador->id,
            'validado_at'   => now(),
        ]);

        // Avanzar la solicitud al siguiente estado
        $pago->solicitud->update([
            'estado'      => EstadoSolicitudEnum::Aprobada,
            'reviewed_by' => $validador->id,
            'reviewed_at' => now(),
        ]);
    }

    public function rechazar(PagoSoporte $pago, User $validador, ?string $observaciones): void
    {
        // Actualizar estado del soporte
        $pago->update([
            'estado'        => EstadoPagoEnum::Rechazado,
            'observaciones' => $observaciones,
            'validado_por'  => $validador->id,
            'validado_at'   => now(),
        ]);

        // Devolver la solicitud a pendiente_pago para que el funcionario
        // pueda subir un nuevo soporte corregido
        $pago->solicitud->update([
            'estado'        => EstadoSolicitudEnum::PendientePago,
            'motivo_rechazo' => $observaciones,
            'reviewed_by'   => $validador->id,
            'reviewed_at'   => now(),
        ]);
    }
}
