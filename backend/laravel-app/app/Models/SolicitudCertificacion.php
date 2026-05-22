<?php

namespace App\Models;

use App\Enums\EstadoSolicitudEnum;
use App\Enums\TipoCertificadoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudCertificacion extends Model
{
    protected $table = 'solicitudes_certificacion';

    protected $fillable = [
        'funcionario_id',
        'tipo_certificado',
        'estado',
        'requiere_pago',
        'requiere_salario',
        'observaciones',
        'motivo_rechazo',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo_certificado' => TipoCertificadoEnum::class,
            'estado'           => EstadoSolicitudEnum::class,
            'requiere_pago'    => 'boolean',
            'requiere_salario' => 'boolean',
            'reviewed_at'      => 'datetime',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pagoSoporte(): HasOne
    {
        return $this->hasOne(PagoSoporte::class);
    }

    public function certificado(): HasOne
    {
        return $this->hasOne(Certificado::class);
    }
}
