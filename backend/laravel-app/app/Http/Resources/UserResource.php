<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'documento' => $this->documento,   // cédula — identificador principal
            'telefono' => $this->telefono,
            'estado' => $this->estado,
            'must_change_password' => (bool) $this->must_change_password,
            'password_changed_at' => $this->password_changed_at?->toISOString(),
            'roles' => $this->getRoleNames(),
            'permisos' => $this->getAllPermissions()->pluck('name'),
            'funcionario' => $this->when(! $this->must_change_password && $this->relationLoaded('funcionario'),
                fn () => new FuncionarioResource($this->funcionario)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
