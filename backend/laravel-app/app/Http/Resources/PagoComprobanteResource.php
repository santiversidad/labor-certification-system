<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoComprobanteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'version' => $this->version,
            'nombre_original' => $this->nombre_original, 'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes, 'sha256' => $this->sha256,
            'referencia' => $this->referencia, 'observacion_funcionario' => $this->observacion_funcionario,
            'estado' => $this->estado, 'motivo_rechazo' => $this->motivo_rechazo,
            'revisado_at' => $this->revisado_at?->toISOString(),
            'revisor' => $this->whenLoaded('revisor', fn () => $this->revisor ? ['id' => $this->revisor->id, 'name' => $this->revisor->name] : null),
            'created_at' => $this->created_at?->toISOString(),
            'archivo_url' => "/api/v1/solicitudes/{$this->soporte->solicitud_certificacion_id}/pago/comprobantes/{$this->id}",
        ];
    }
}
