<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\TokenValidacion;
use App\Models\User;

class TokenValidacionService
{
    public function crearParaCertificado(Certificado $certificado, ?User $creadoPor = null, ?string $token = null): string
    {
        $token ??= bin2hex(random_bytes(32));

        TokenValidacion::create([
            'certificado_id' => $certificado->id,
            'token_hash'     => $this->hash($token),
            'tipo'           => 'validacion',
            'created_by'     => $creadoPor?->id,
        ]);

        return $token;
    }

    public function buscarTokenVigente(string $token): ?TokenValidacion
    {
        return TokenValidacion::with('certificado.funcionario', 'certificado.solicitud')
            ->where('token_hash', $this->hash($token))
            ->where('tipo', 'validacion')
            ->first();
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
