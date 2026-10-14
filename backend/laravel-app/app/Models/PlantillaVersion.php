<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaVersion extends Model
{
    protected $table = 'plantilla_versiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['contenido' => 'array', 'revision' => 'integer', 'publicado_at' => 'datetime'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaCertificado::class, 'plantilla_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editado_por');
    }
}
