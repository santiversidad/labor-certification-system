<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualActualizacionAsignacion extends Model
{
    protected $table = 'manual_actualizaciones_asignaciones';

    protected $fillable = [
        'from_version_id', 'to_version_id', 'funcionario_cargo_id', 'ficha_anterior_id',
        'ficha_candidata_id', 'clasificacion', 'motivos', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['motivos' => 'array', 'resolved_at' => 'datetime'];
    }
}
