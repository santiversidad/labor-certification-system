<?php

namespace App\Models;

use App\Enums\TipoActuacionAdministrativaEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActuacionAdministrativa extends Model
{
    protected $table = 'actuaciones_administrativas';

    protected $fillable = [
        'funcionario_id',
        'tipo_actuacion',
        'numero_acto',
        'fecha_acto',
        'descripcion',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tipo_actuacion' => TipoActuacionAdministrativaEnum::class,
            'fecha_acto'     => 'date',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
