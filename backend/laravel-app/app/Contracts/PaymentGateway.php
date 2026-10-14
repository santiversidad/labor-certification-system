<?php

namespace App\Contracts;

use App\Enums\EstadoOrdenPagoEnum;
use App\Models\OrdenPagoCertificado;

interface PaymentGateway
{
    /** @return array{referencia_proveedor:string, url_pago:string|null, metadata?:array<string, mixed>} */
    public function crearOrden(OrdenPagoCertificado $orden): array;

    public function consultarEstado(OrdenPagoCertificado $orden): EstadoOrdenPagoEnum;

    /** La firma y autenticidad del webhook deben validarse antes de confirmar un pago. */
    public function validarWebhook(string $payload, array $headers): bool;
}
