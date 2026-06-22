<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/auditoria
     *
     * Solo accesible por admin.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('auditoria.ver')) {
            return $this->forbiddenResponse('No tiene permisos para consultar la auditoría.');
        }

        $logs = AuditLog::with('user')
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->accion))
            ->when($request->filled('modelo'), fn ($q) => $q->where('modelo', $request->modelo))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->successResponse(
            collect([
                'data' => $logs->map(fn ($log) => [
                    'id'          => $log->id,
                    'accion'      => $log->accion,
                    'modelo'      => $log->modelo,
                    'modelo_id'   => $log->modelo_id,
                    'descripcion' => $log->descripcion,
                    'created_at'  => $log->created_at?->toISOString(),
                    'user'        => $log->user ? [
                        'id'        => $log->user->id,
                        'name'      => $log->user->name,
                        'documento' => $log->user->documento,
                    ] : null,
                ])->values()->all(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                    'last_page'    => $logs->lastPage(),
                ],
            ])->toArray(),
            'Registros de auditoría consultados correctamente.'
        );
    }
}
