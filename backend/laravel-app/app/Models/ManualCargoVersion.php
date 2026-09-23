<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualCargoVersion extends Model
{
    protected $table = 'manual_cargo_versiones';

    protected $fillable = [
        'manual_funciones_version_id', 'cargo_id', 'proposito_principal', 'requisitos',
        'source_id', 'import_key', 'content_hash', 'natural_key', 'denominacion_fuente',
        'dependencia', 'area_funcional', 'numero_cargos', 'jefe_inmediato', 'metadata_manual',
    ];

    protected function casts(): array
    {
        return ['metadata_manual' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ManualFuncionVersion::class, 'manual_funciones_version_id');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function funciones(): HasMany
    {
        return $this->hasMany(ManualFuncionEsencial::class, 'manual_cargo_version_id')->orderBy('orden');
    }

    public function sucesores(): HasMany
    {
        return $this->hasMany(ManualCargoLineage::class, 'predecessor_id');
    }

    public function antecesores(): HasMany
    {
        return $this->hasMany(ManualCargoLineage::class, 'successor_id');
    }
}
