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
            'documento' => $this->documento,
            'username' => $this->username,
            'tipo_cuenta' => $this->tipo_cuenta,
            'email' => $this->email,
            'funcionario_id' => $this->tipo_cuenta === 'funcionario' ? $this->funcionario?->id : null,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'desactivado_at' => $this->desactivado_at?->toISOString(),
            'desactivado_por' => $this->desactivado_por,
            'motivo_desactivacion' => $this->motivo_desactivacion,   // cédula — identificador principal
            'telefono' => $this->telefono,
            'estado' => $this->estado,
            'must_change_password' => (bool) $this->must_change_password,
            'mfa_enabled' => (bool) $this->mfa_confirmed_at,
            'mfa_pending' => $request->hasSession() && (bool) $request->session()->get('pending_auth'),
            'password_changed_at' => $this->password_changed_at?->toISOString(),
            'roles' => $this->getRoleNames(),
            'permisos' => $this->getAllPermissions()->pluck('name'),
            'funcionario' => $this->when($this->tipo_cuenta === 'funcionario' && ! $this->must_change_password && $this->relationLoaded('funcionario'),
                fn () => new FuncionarioResource($this->funcionario)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
