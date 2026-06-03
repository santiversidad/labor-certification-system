<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudCertificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'tipo_certificado' => $this->tipo_certificado,
            'estado'           => $this->estado,
            'requiere_pago'    => $this->requiere_pago,
            'requiere_salario' => $this->requiere_salario,
            'observaciones'    => $this->observaciones,
            'motivo_rechazo'   => $this->motivo_rechazo,
            'reviewed_at'      => $this->reviewed_at?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),

            // Relaciones opcionales (se incluyen solo si fueron cargadas)
            'funcionario'  => $this->whenLoaded('funcionario',
                fn () => new FuncionarioResource($this->funcionario)
            ),
            'creado_por'   => $this->whenLoaded('creadoPor', function () {
                return [
                    'id'       => $this->creadoPor->id,
                    'name'     => $this->creadoPor->name,
                    'documento' => $this->creadoPor->documento,
                ];
            }),
            'revisado_por' => $this->whenLoaded('revisadoPor', function () {
                return [
                    'id'       => $this->revisadoPor->id,
                    'name'     => $this->revisadoPor->name,
                    'documento' => $this->revisadoPor->documento,
                ];
            }),
            'pago_soporte' => $this->whenLoaded('pagoSoporte',
                fn () => new PagoSoporteResource($this->pagoSoporte)
            ),
            'certificado'  => $this->whenLoaded('certificado',
                fn () => new CertificadoResource($this->certificado)
            ),
        ];
    }
}
