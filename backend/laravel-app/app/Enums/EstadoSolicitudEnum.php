<?php

namespace App\Enums;

enum EstadoSolicitudEnum: string
{
    case Pendiente      = 'pendiente';
    case EnRevision     = 'en_revision';
    case RequierePago   = 'requiere_pago';
    case PagoPendiente  = 'pago_pendiente';
    case PagoValidado   = 'pago_validado';
    case Aprobado       = 'aprobado';
    case Rechazado      = 'rechazado';
    case Generado       = 'generado';
    case Cancelado      = 'cancelado';

    public function label(): string
    {
        return match($this) {
            self::Pendiente     => 'Pendiente',
            self::EnRevision    => 'En revisión',
            self::RequierePago  => 'Requiere pago',
            self::PagoPendiente => 'Pago pendiente',
            self::PagoValidado  => 'Pago validado',
            self::Aprobado      => 'Aprobado',
            self::Rechazado     => 'Rechazado',
            self::Generado      => 'Generado',
            self::Cancelado     => 'Cancelado',
        };
    }
}
