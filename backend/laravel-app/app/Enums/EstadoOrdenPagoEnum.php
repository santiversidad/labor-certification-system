<?php

namespace App\Enums;

enum EstadoOrdenPagoEnum: string
{
    case Pendiente = 'pendiente';
    case Procesando = 'procesando';
    case Confirmado = 'confirmado';
    case Fallido = 'fallido';
    case Cancelado = 'cancelado';
    case Expirado = 'expirado';
}
