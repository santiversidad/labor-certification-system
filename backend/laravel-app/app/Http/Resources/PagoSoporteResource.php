<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoSoporteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'solicitud_id'            => $this->solicitud_certificacion_id,
            'archivo_original_nombre' => $this->archivo_original_nombre,
            // La ruta física (archivo_path) nunca se expone al frontend
            'estado'                  => $this->estado,
            'observaciones'           => $this->observaciones,
            'validado_at'             => $this->validado_at?->toISOString(),
            'created_at'              => $this->created_at?->toISOString(),
            'validado_por'            => $this->whenLoaded('validadoPor', fn () => [
                'id'   => $this->validadoPor->id,
                'name' => $this->validadoPor->name,
            ]),
            'funcionario'             => $this->whenLoaded('funcionario', fn () => [
                'id'     => $this->funcionario->id,
                'nombres' => $this->funcionario->nombres,
                'apellidos' => $this->funcionario->apellidos,
                'numero_documento' => $this->funcionario->numero_documento,
            ]),
        ];
    }
}
