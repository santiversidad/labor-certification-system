<?php

namespace App\Enums;

enum EstadoSolicitudEnum: string
{
    case Pendiente           = 'pendiente';
    case EnRevision          = 'en_revision';
    case PendientePago       = 'pendiente_pago';
    case PagoEnRevision      = 'pago_en_revision';
    case Aprobada            = 'aprobada';
    case Rechazada           = 'rechazada';
    case CertificadoGenerado = 'certificado_generado';
    case Cerrada             = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente           => 'Pendiente',
            self::EnRevision          => 'En revision',
            self::PendientePago       => 'Pendiente de pago',
            self::PagoEnRevision      => 'Pago en revision',
            self::Aprobada            => 'Aprobada',
            self::Rechazada           => 'Rechazada',
            self::CertificadoGenerado => 'Certificado generado',
            self::Cerrada             => 'Cerrada',
        };
    }

    public static function fromInput(string $estado): self
    {
        return match ($estado) {
            'requiere_pago', 'pago_pendiente', 'pendiente_pago' => self::PendientePago,
            'pago_validado', 'aprobado', 'aprobada' => self::Aprobada,
            'rechazado', 'rechazada' => self::Rechazada,
            'generado', 'certificado_generado' => self::CertificadoGenerado,
            'cancelado', 'cerrada' => self::Cerrada,
            default => self::from($estado),
        };
    }

    public function esTerminal(): bool
    {
        return in_array($this, [
            self::Rechazada,
            self::CertificadoGenerado,
            self::Cerrada,
        ], true);
    }
}
