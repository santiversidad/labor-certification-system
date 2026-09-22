<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualCargoLineage extends Model
{
    protected $table = 'manual_cargo_lineages';

    protected $fillable = [
        'predecessor_id', 'successor_id', 'clasificacion', 'estado', 'puntaje',
        'diferencias', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['diferencias' => 'array', 'puntaje' => 'decimal:2', 'resolved_at' => 'datetime'];
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(ManualCargoVersion::class, 'predecessor_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(ManualCargoVersion::class, 'successor_id');
    }
}
