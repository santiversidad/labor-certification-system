<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaCertificado extends Model
{
    protected $table = 'plantillas_certificado';

    protected $guarded = ['id'];

    public function publicada(): BelongsTo
    {
        return $this->belongsTo(PlantillaVersion::class, 'publicada_id');
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(PlantillaVersion::class, 'plantilla_id');
    }
}
