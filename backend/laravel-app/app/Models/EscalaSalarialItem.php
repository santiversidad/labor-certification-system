<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EscalaSalarialItem extends Model
{
    protected $attributes = ['origen_valor' => 'pendiente', 'revision' => 1];

    protected $table = 'escala_salarial_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['asignacion_basica_mensual' => 'decimal:0', 'numero_cargos' => 'integer', 'revision' => 'integer'];
    }
}
