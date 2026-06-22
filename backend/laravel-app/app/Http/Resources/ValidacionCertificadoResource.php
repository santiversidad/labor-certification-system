<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidacionCertificadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'valido'           => $data['valido'],
            'codigo_unico'     => $data['codigo_unico'] ?? null,
            'fecha_generacion' => $data['fecha_generacion'] ?? null,
            'estado'           => $data['estado'] ?? null,
            'mensaje'          => $data['mensaje'] ?? null,
            'funcionario'      => $data['funcionario'] ?? null,
        ];
    }
}
