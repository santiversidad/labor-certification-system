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
        'radicado',
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

    // ─── Eventos ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $solicitud): void {
            if (empty($solicitud->radicado)) {
                $anio        = now()->year;
                $consecutivo = static::whereYear('created_at', $anio)->count() + 1;
                $solicitud->radicado = sprintf('CL-%d-%06d', $anio, $consecutivo);
            }
        });
    }

    // ─── Consultas de negocio ────────────────────────────────────────────────

    /** Estados que bloquean una nueva solicitud del mismo funcionario. */
    public static function estadosActivos(): array
    {
        return [
            EstadoSolicitudEnum::Pendiente->value,
            EstadoSolicitudEnum::EnRevision->value,
            EstadoSolicitudEnum::PendientePago->value,
            EstadoSolicitudEnum::PagoEnRevision->value,
            EstadoSolicitudEnum::Aprobada->value,
        ];
    }

    public static function tieneActivaPara(int $funcionarioId): bool
    {
        return static::where('funcionario_id', $funcionarioId)
            ->whereIn('estado', static::estadosActivos())
            ->exists();
    }

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

    public function pagoAprobado(): HasOne
    {
        return $this->hasOne(PagoSoporte::class)->where('estado', \App\Enums\EstadoPagoEnum::Aprobado->value);
    }

    public function certificado(): HasOne
    {
        return $this->hasOne(Certificado::class);
    }
}
