<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaAsset extends Model
{
    protected $table = 'plantilla_assets';

    protected $guarded = ['id'];

    protected $hidden = ['ruta'];
}
