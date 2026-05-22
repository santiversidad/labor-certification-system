<?php

namespace App\Actions;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
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
        return AuditLog::create([
            'user_id'     => Auth::id(),
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
