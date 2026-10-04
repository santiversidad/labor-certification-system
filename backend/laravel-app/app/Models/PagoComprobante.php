<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoComprobante extends Model
{
    protected $table = 'pago_comprobantes';

    protected $fillable = [
        'pago_soporte_id', 'version', 'archivo_path', 'nombre_original', 'mime_type',
        'size_bytes', 'sha256', 'referencia', 'observacion_funcionario', 'estado',
        'motivo_rechazo', 'revisado_por', 'revisado_at',
    ];

    protected $hidden = ['archivo_path'];

    protected function casts(): array
    {
        return ['revisado_at' => 'datetime'];
    }

    public function soporte(): BelongsTo
    {
        return $this->belongsTo(PagoSoporte::class, 'pago_soporte_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
