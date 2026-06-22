<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificadoResource;
use App\Models\Certificado;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/certificados
     *
     * Admin/secretario: todos los certificados.
     * Funcionario: solo los propios.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Certificado::with(['generadoPor', 'solicitud', 'funcionario'])
            ->latest();

        if ($user->hasRole('funcionario')) {
            $query->where('funcionario_id', $user->funcionario?->id);
        }

        $certificados = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            CertificadoResource::collection($certificados)->response()->getData(true),
            'Certificados consultados correctamente.'
        );
    }

    /**
     * POST /api/v1/solicitudes/{solicitud}/generar-certificado
     *
     * TODO Fase 3: implementar GenerarCertificadoService, PDF y token.
     */
    public function generar(Request $request, int $solicitud): JsonResponse
    {
        return $this->errorResponse('Generación de certificados disponible en próxima versión.', null, 501);
    }

    /**
     * GET /api/v1/certificados/{certificado}
     */
    public function show(Request $request, int $certificado): JsonResponse
    {
        $model = Certificado::with(['generadoPor', 'solicitud', 'funcionario'])
            ->findOrFail($certificado);

        $user = $request->user();

        if ($user->hasRole('funcionario') && $model->funcionario_id !== $user->funcionario?->id) {
            return $this->forbiddenResponse('No tiene permiso para ver este certificado.');
        }

        return $this->successResponse(
            new CertificadoResource($model),
            'Certificado consultado correctamente.'
        );
    }
}
