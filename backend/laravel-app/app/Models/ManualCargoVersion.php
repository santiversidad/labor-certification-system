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
    ];

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
}
