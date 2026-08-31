<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualFuncionEsencial extends Model
{
    protected $table = 'manual_funciones_esenciales';

    protected $fillable = ['manual_cargo_version_id', 'orden', 'descripcion'];

    public function cargoVersion(): BelongsTo
    {
        return $this->belongsTo(ManualCargoVersion::class, 'manual_cargo_version_id');
    }
}
