<?php

namespace App\Actions;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class RegistrarAuditoriaAction
{
    public function execute(
        string $accion,
        string $modelo,
        ?int $modeloId = null,
        ?string $descripcion = null,
        ?array $metadata = null,
    ): AuditLog {
        // Request::user() respeta el guard activo en la petición (Sanctum en API).
        // Es más fiable que Auth::id() que podría resolver el guard 'web' en tests.
        $userId = Request::user()?->getKey();

        return AuditLog::create([
            'user_id'     => $userId,
            'accion'      => $accion,
            'modelo'      => $modelo,
            'modelo_id'   => $modeloId,
            'descripcion' => $descripcion,
            'metadata'    => $metadata,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
        ]);
    }
}
