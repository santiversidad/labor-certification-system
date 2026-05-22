<?php

namespace App\Models;

use App\Enums\EstadoFuncionarioEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Funcionario extends Model
{
    protected $table = 'funcionarios';

    protected $fillable = [
        'user_id',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'correo_institucional',
        'telefono',
        'estado',
        'fecha_ingreso',
        'fecha_retiro',
        'dependencia',
        'cargo_id',
    ];

    protected function casts(): array
    {
        return [
            'estado'        => EstadoFuncionarioEnum::class,
            'fecha_ingreso' => 'date',
            'fecha_retiro'  => 'date',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudCertificacion::class);
    }

    public function actuaciones(): HasMany
    {
        return $this->hasMany(ActuacionAdministrativa::class);
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActivos($query)
    {
        return $query->where('estado', EstadoFuncionarioEnum::Activo->value);
    }

    public function scopePorDocumento($query, string $documento)
    {
        return $query->where('numero_documento', $documento);
    }
}
