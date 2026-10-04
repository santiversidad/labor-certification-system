<?php

namespace App\Models;

use App\Enums\EstadoFuncionarioEnum;
use App\Exceptions\IdentityException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Funcionario extends Model
{
    protected static function booted(): void
    {
        static::saving(function (Funcionario $employee) {
            if ($employee->user_id && User::find($employee->user_id)?->tipo_cuenta !== 'funcionario') {
                throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'Solo una cuenta funcionario admite vínculo laboral.');
            }
        });
    }

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
            'estado' => EstadoFuncionarioEnum::class,
            'fecha_ingreso' => 'date',
            'fecha_retiro' => 'date',
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

    public function historialCargos(): HasMany
    {
        return $this->hasMany(FuncionarioCargo::class);
    }

    public function cargoBase(): HasOne
    {
        return $this->hasOne(FuncionarioCargo::class)->where('es_cargo_base', true)->latestOfMany('fecha_inicio');
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }

    public function pagosSoportes(): HasMany
    {
        return $this->hasMany(PagoSoporte::class);
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
