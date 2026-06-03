<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoSoporteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'solicitud_id'           => $this->solicitud_certificacion_id,
            'archivo_original_nombre' => $this->archivo_original_nombre,
            'estado'                 => $this->estado,
            'observaciones'          => $this->observaciones,
            'validado_at'            => $this->validado_at?->toISOString(),
            'created_at'             => $this->created_at?->toISOString(),
            'validado_por'           => $this->whenLoaded('validadoPor', function () {
                return [
                    'id'   => $this->validadoPor->id,
                    'name' => $this->validadoPor->name,
                ];
            }),
        ];
    }
}
