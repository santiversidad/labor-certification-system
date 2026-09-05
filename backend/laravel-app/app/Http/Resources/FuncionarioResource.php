<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuncionarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vigentes = $this->historialCargos()->whereDate('fecha_inicio', '<=', today())
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', today()))->get();
        $asignacion = $vigentes->count() === 1 ? $vigentes->sole() : null;

        return [
            'asignacion_actual' => $asignacion ? [
                'id' => $asignacion->id, 'manual_cargo_version_id' => $asignacion->manual_cargo_version_id,
                'tipo_vinculacion' => $asignacion->tipo_vinculacion, 'naturaleza_cargo' => $asignacion->naturaleza_cargo,
            ] : null,
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => "{$this->nombres} {$this->apellidos}",
            'correo_institucional' => $this->correo_institucional,
            'telefono' => $this->telefono,
            'estado' => $this->estado,
            'fecha_ingreso' => $this->fecha_ingreso?->toDateString(),
            'fecha_retiro' => $this->fecha_retiro?->toDateString(),
            'dependencia' => $this->dependencia,
            'cargo' => $this->whenLoaded('cargo', fn () => new CargoResource($this->cargo)),
            'usuario' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'estado' => (bool) $this->user->estado,
                'must_change_password' => (bool) $this->user->must_change_password,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
