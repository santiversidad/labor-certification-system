<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualFuncion extends Model
{
    protected $table = 'manuales_funciones';

    protected $fillable = ['codigo', 'nombre', 'descripcion'];

    public function versiones(): HasMany
    {
        return $this->hasMany(ManualFuncionVersion::class, 'manual_funciones_id');
    }
}
