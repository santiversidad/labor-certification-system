<?php

namespace App\Models;

use App\Enums\EstadoCertificadoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $hidden = [
        'archivo_pdf_path',
        'hash_pdf',
    ];

    protected function casts(): array
    {
        return [
            'estado'           => EstadoCertificadoEnum::class,
            'fecha_generacion' => 'datetime',
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

    public function tokens(): HasMany
    {
        return $this->hasMany(TokenValidacion::class);
    }
}
