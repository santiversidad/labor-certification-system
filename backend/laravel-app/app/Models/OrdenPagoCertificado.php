<?php

namespace App\Models;

use App\Enums\EstadoOrdenPagoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenPagoCertificado extends Model
{
    protected $table = 'ordenes_pago_certificado';

    protected $fillable = [
        'solicitud_certificacion_id', 'referencia', 'estado', 'monto', 'moneda',
        'proveedor', 'referencia_proveedor', 'confirmado_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoOrdenPagoEnum::class,
            'monto' => 'decimal:2',
            'confirmado_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCertificacion::class, 'solicitud_certificacion_id');
    }
}
