<?php

namespace App\Models;

use App\Enums\EstadoPagoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoSoporte extends Model
{
    protected $table = 'pagos_soportes';

    protected $fillable = [
        'solicitud_certificacion_id',
        'funcionario_id',
        'archivo_path',
        'archivo_original_nombre',
        'estado',
        'observaciones',
        'validado_por',
        'validado_at',
    ];

    protected $hidden = [
        'archivo_path',
    ];

    protected function casts(): array
    {
        return [
            'estado'       => EstadoPagoEnum::class,
            'validado_at'  => 'datetime',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCertificacion::class, 'solicitud_certificacion_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }
}
