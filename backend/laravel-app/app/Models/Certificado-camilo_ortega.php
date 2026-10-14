<?php

namespace App\Models;

use App\Enums\EstadoCertificadoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Certificado extends Model
{
    protected $table = 'certificados';

    protected $fillable = [
        'solicitud_certificacion_id',
        'funcionario_id',
        'codigo_unico',
        'archivo_pdf_path',
        'hash_pdf',
        'fecha_generacion',
        'generado_por',
        'estado',
        'motivo_anulacion',
        'anulado_por',
        'anulado_at',
        'snapshot_schema_version',
        'snapshot_datos',
    ];

    protected $hidden = [
        'archivo_pdf_path',
        'hash_pdf',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $certificado): void {
            if ($certificado->snapshot_schema_version === null || $certificado->snapshot_datos === null) {
                throw new \DomainException('No se puede generar un certificado nuevo sin snapshot versionado.');
            }
        });

        static::updating(function (self $certificado): void {
            if ($certificado->isDirty(['snapshot_schema_version', 'snapshot_datos'])) {
                throw new \DomainException('El snapshot de un certificado emitido es inmutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoCertificadoEnum::class,
            'fecha_generacion' => 'datetime',
            'anulado_at' => 'datetime',
            'snapshot_schema_version' => 'integer',
            'snapshot_datos' => 'array',
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

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(TokenValidacion::class);
    }

    public function tokenValidacion(): HasOne
    {
        return $this->hasOne(TokenValidacion::class)->where('tipo', 'validacion')->latestOfMany();
    }
}
