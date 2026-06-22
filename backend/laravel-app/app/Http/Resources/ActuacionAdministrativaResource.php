<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActuacionAdministrativaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'funcionario_id' => $this->funcionario_id,
            'tipo_actuacion'=> $this->tipo_actuacion?->value ?? $this->tipo_actuacion,
            'numero_acto'   => $this->numero_acto,
            'fecha_acto'    => $this->fecha_acto?->toDateString(),
            'descripcion'   => $this->descripcion,
            'funcionario'   => $this->whenLoaded('funcionario', fn () => new FuncionarioResource($this->funcionario)),
            'creado_por'    => $this->whenLoaded('creadoPor', fn () => $this->creadoPor ? [
                'id'   => $this->creadoPor->id,
                'name' => $this->creadoPor->name,
            ] : null),
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
