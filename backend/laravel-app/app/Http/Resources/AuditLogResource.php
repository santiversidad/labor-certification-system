<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user'        => $this->whenLoaded('user', fn () => $this->user ? [
                'id'        => $this->user->id,
                'name'      => $this->user->name,
                'documento' => $this->user->documento,
            ] : null),
            'accion'      => $this->accion,
            'entidad'     => $this->modelo,
            'modelo'      => $this->modelo,
            'entidad_id'  => $this->modelo_id,
            'modelo_id'   => $this->modelo_id,
            'ip'          => $this->ip_address,
            'ip_address'  => $this->ip_address,
            'user_agent'  => $this->user_agent,
            'descripcion' => $this->descripcion,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
