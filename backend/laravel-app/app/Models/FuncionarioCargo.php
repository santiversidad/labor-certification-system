<?php

namespace App\Models;

use App\Enums\NaturalezaCargoEnum;
use App\Enums\TipoVinculacionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioCargo extends Model
{
    protected $table = 'funcionario_cargo';

    protected $fillable = [
        'funcionario_id',
        'cargo_id',
        'tipo_vinculacion',
        'naturaleza_cargo',
        'es_cargo_base',
        'es_encargo',
        'fecha_inicio',
        'fecha_fin',
        'acto_administrativo_id',
        'salario_override',
    ];

    protected function casts(): array
    {
        return [
            'tipo_vinculacion' => TipoVinculacionEnum::class,
            'naturaleza_cargo' => NaturalezaCargoEnum::class,
            'es_cargo_base'    => 'boolean',
            'es_encargo'       => 'boolean',
            'fecha_inicio'     => 'date',
            'fecha_fin'        => 'date',
            'salario_override' => 'decimal:2',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function actoAdministrativo(): BelongsTo
    {
        return $this->belongsTo(ActuacionAdministrativa::class, 'acto_administrativo_id');
    }
}
