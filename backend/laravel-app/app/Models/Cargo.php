<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cargo extends Model
{
    protected $table = 'cargos';

    protected $fillable = [
        'codigo',
        'grado',
        'denominacion',
        'nivel',
        'dependencia',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class);
    }
}
