<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EscalaSalarialVigencia extends Model
{
    protected $attributes = ['estado' => 'borrador', 'revision' => 1];

    protected $table = 'escala_salarial_vigencias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['anio' => 'integer', 'revision' => 'integer', 'porcentaje_referencia' => 'decimal:2', 'fecha_vigencia' => 'date:Y-m-d'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EscalaSalarialItem::class, 'vigencia_id');
    }
}
