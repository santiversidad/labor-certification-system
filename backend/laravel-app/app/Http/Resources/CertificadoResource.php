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
            'estado'           => $this->estado?->value ?? $this->estado,
            'fecha_generacion' => $this->fecha_generacion?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
            'motivo_anulacion' => $this->motivo_anulacion,
            'anulado_at'       => $this->anulado_at?->toISOString(),
            'generado_por'     => $this->whenLoaded('generadoPor', function () {
                return [
                    'id'   => $this->generadoPor->id,
                    'name' => $this->generadoPor->name,
                ];
            }),
            'anulado_por'      => $this->whenLoaded('anuladoPor', fn () => $this->anuladoPor ? [
                'id'   => $this->anuladoPor->id,
                'name' => $this->anuladoPor->name,
            ] : null),
            'funcionario'      => $this->whenLoaded('funcionario', fn () => $this->funcionario ? [
                'id'              => $this->funcionario->id,
                'nombres'         => $this->funcionario->nombres,
                'apellidos'       => $this->funcionario->apellidos,
                'numero_documento'=> $this->funcionario->numero_documento,
            ] : null),
            'solicitud'        => $this->whenLoaded('solicitud', fn () => $this->solicitud ? [
                'id'       => $this->solicitud->id,
                'radicado' => $this->solicitud->radicado,
                'estado'   => $this->solicitud->estado?->value ?? $this->solicitud->estado,
            ] : null),
        ];
    }
}
