<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuncionarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'user_id'              => $this->user_id,
            'tipo_documento'       => $this->tipo_documento,
            'numero_documento'     => $this->numero_documento,
            'nombres'              => $this->nombres,
            'apellidos'            => $this->apellidos,
            'nombre_completo'      => "{$this->nombres} {$this->apellidos}",
            'correo_institucional' => $this->correo_institucional,
            'telefono'             => $this->telefono,
            'estado'               => $this->estado,
            'fecha_ingreso'        => $this->fecha_ingreso?->toDateString(),
            'fecha_retiro'         => $this->fecha_retiro?->toDateString(),
            'dependencia'          => $this->dependencia,
            'cargo'                => $this->whenLoaded('cargo', fn () => new CargoResource($this->cargo)),
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
