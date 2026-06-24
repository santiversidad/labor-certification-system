<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RangoSalarialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'codigo'         => $this->codigo,
            'grado'          => $this->grado,
            'denominacion'   => $this->denominacion,
            'vigencia_anio'  => $this->vigencia_anio,
            'salario_basico' => $this->salario_basico,
            'moneda'         => $this->moneda,
            'observaciones'  => $this->observaciones,
            'estado'         => $this->estado,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
