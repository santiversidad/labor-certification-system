<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'codigo_unico'     => $this->codigo_unico,
            'estado'           => $this->estado,
            'fecha_generacion' => $this->fecha_generacion?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
            'generado_por'     => $this->whenLoaded('generadoPor', function () {
                return [
                    'id'   => $this->generadoPor->id,
                    'name' => $this->generadoPor->name,
                ];
            }),
        ];
    }
}
