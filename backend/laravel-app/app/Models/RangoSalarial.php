<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RangoSalarial extends Model
{
    use HasFactory;

    protected $table = 'rangos_salariales';

    protected $fillable = [
        'codigo',
        'grado',
        'vigencia_anio',
        'salario_basico',
        'moneda',
        'observaciones',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_anio'  => 'integer',
            'salario_basico' => 'decimal:2',
            'estado'         => 'boolean',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
