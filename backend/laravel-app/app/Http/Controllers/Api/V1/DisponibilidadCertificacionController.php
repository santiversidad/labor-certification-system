<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DisponibilidadCertificacionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisponibilidadCertificacionController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request, DisponibilidadCertificacionService $service): JsonResponse
    {
        abort_unless($request->user()->can('solicitudes.crear'), 403);

        $funcionario = $request->user()->funcionario;
        if (! $funcionario) {
            return $this->errorResponse(
                'Su usuario no tiene un registro de funcionario asociado. Contacte al administrador.',
                null,
                422,
                'FUNCIONARIO_NOT_LINKED',
            );
        }

        return $this->successResponse(
            $service->consultar($funcionario->id),
            'Disponibilidad consultada correctamente.',
        );
    }
}
