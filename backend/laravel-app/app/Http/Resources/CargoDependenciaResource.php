<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CargoDependenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'denominacion' => $this->denominacion,
            'codigo' => $this->codigo, 'grado' => $this->grado,
            'perfiles_count' => (int) $this->perfiles_count];
    }
}
