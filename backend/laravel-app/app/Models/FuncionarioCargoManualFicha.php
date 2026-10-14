<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioCargoManualFicha extends Model
{
    protected $table = 'funcionario_cargo_manual_fichas';

    protected $fillable = [
        'funcionario_cargo_id', 'manual_cargo_version_id', 'vigencia_desde', 'vigencia_hasta',
        'origen', 'lineage_id', 'resolved_by', 'resolved_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_desde' => 'date', 'vigencia_hasta' => 'date', 'resolved_at' => 'datetime', 'metadata' => 'array',
        ];
    }

    public function asignacionLaboral(): BelongsTo
    {
        return $this->belongsTo(FuncionarioCargo::class, 'funcionario_cargo_id');
    }

    public function fichaManual(): BelongsTo
    {
        return $this->belongsTo(ManualCargoVersion::class, 'manual_cargo_version_id');
    }
}
