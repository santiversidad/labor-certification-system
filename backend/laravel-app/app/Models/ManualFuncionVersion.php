<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualFuncionVersion extends Model
{
    protected $table = 'manual_funciones_versiones';

    protected $fillable = [
        'manual_funciones_id', 'version', 'vigencia_desde', 'vigencia_hasta',
        'acto_tipo', 'acto_numero', 'acto_fecha', 'acto_referencia', 'estado',
        'created_by', 'updated_by',
        'metadata_manual',
    ];

    protected function casts(): array
    {
        return [
            'metadata_manual' => 'array',
            'vigencia_desde' => 'date',
            'vigencia_hasta' => 'date',
            'acto_fecha' => 'date',
        ];
    }

    public function manual(): BelongsTo
    {
        return $this->belongsTo(ManualFuncion::class, 'manual_funciones_id');
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(ManualCargoVersion::class, 'manual_funciones_version_id');
    }
}
