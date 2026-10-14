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
        return ['metadata_manual' => 'array', 'verificacion_documental' => 'array', 'pagina_inicio' => 'integer'];
    }

    public function paginaVerificada(): bool
    {
        return $this->pagina_inicio > 0
            && is_string($this->content_hash) && strlen($this->content_hash) === 64
            && ($this->verificacion_documental['estado'] ?? null) === 'verificada'
            && preg_match('/^[a-f0-9]{64}$/', $this->verificacion_documental['pdf_sha256'] ?? '') === 1
            && ($this->verificacion_documental['content_hash'] ?? null) === $this->content_hash;
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
