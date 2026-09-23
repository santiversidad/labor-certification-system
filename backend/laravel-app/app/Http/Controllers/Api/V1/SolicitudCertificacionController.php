<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Http\Requests\StoreSolicitudCertificacionRequest;
use App\Http\Resources\CertificadoResource;
use App\Http\Resources\SolicitudCertificacionResource;
use App\Models\SolicitudCertificacion;
use App\Services\ExpedirCertificacionService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SolicitudCertificacionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ExpedirCertificacionService $expedirCertificacion) {}

    public function index(IndexQueryRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SolicitudCertificacion::class);
        $query = SolicitudCertificacion::with(['funcionario.cargo', 'creadoPor', 'revisadoPor', 'certificado', 'ordenPago'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->validated('estado')))
            ->when($request->filled('tipo_certificado'), fn ($q) => $q->where('tipo_certificado', $request->validated('tipo_certificado')))
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->validated('funcionario_id')))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->validated('fecha_desde')))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->validated('fecha_hasta')))
            ->orderBy($request->validated('orden', 'created_at'), $request->validated('direccion', 'desc'));
        $paginator = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            SolicitudCertificacionResource::collection($paginator)->response()->getData(true),
            'Peticiones de expedición consultadas correctamente.'
        );
    }

    public function store(StoreSolicitudCertificacionRequest $request): JsonResponse
    {
        $this->authorize('create', SolicitudCertificacion::class);

        try {
            $resultado = $this->expedirCertificacion->expedir(
                $request->user(),
                $request->validated('tipo_certificado'),
                $request->validated('observaciones'),
            );
        } catch (RuntimeException $exception) {
            return match ($exception->getMessage()) {
                'MONTHLY_CERTIFICATE_LIMIT' => $this->errorResponse(
                    'Ya utilizó este mes el cupo de la modalidad seleccionada.', null, 409, 'MONTHLY_CERTIFICATE_LIMIT'
                ),
                'INACTIVE_EMPLOYEE' => $this->errorResponse(
                    'Su cuenta o vinculación no está activa para expedir certificaciones.', null, 403, 'INACTIVE_EMPLOYEE'
                ),
                'EMPLOYEE_WITHOUT_POSITION' => $this->errorResponse(
                    'No existe un cargo institucional válido asociado a su ficha.', null, 409, 'EMPLOYEE_WITHOUT_POSITION'
                ),
                default => $this->errorResponse(
                    'No fue posible completar la expedición automática. El cupo no fue consumido; intente nuevamente.', null, 503, 'CERTIFICATE_GENERATION_FAILED'
                ),
            };
        } catch (DomainException $exception) {
            $code = preg_match('/^[A-Z_]+$/', $exception->getMessage()) ? $exception->getMessage() : 'CERTIFICATE_SOURCE_CONFLICT';

            return $this->errorResponse($exception->getMessage(), null, 409, $code);
        } catch (\Throwable) {
            return $this->errorResponse(
                'No fue posible completar la expedición automática. El cupo no fue consumido; intente nuevamente.',
                null,
                503,
                'CERTIFICATE_GENERATION_FAILED'
            );
        }

        $payload = [
            'resultado' => $resultado['estado'],
            'solicitud' => new SolicitudCertificacionResource($resultado['solicitud']),
        ];
        if ($resultado['estado'] === 'generada') {
            $payload += [
                'certificado' => new CertificadoResource($resultado['certificado']),
                'descarga_url' => $resultado['download_url'],
                'descarga_expira_en' => $resultado['certificado']->download_token_expires_at?->toISOString(),
            ];
        } else {
            $payload['orden_pago'] = [
                'referencia' => $resultado['orden_pago']->referencia,
                'estado' => $resultado['orden_pago']->estado->value,
            ];
        }

        return $this->createdResponse(
            $payload,
            $resultado['estado'] === 'generada'
                ? 'Certificación generada correctamente.'
                : 'Solicitud registrada. Requiere confirmación de pago antes de generar la certificación.'
        );
    }

    public function show(Request $request, int $solicitud): JsonResponse
    {
        $model = SolicitudCertificacion::with(['funcionario.cargo', 'creadoPor', 'certificado', 'ordenPago'])->findOrFail($solicitud);
        $this->authorize('view', $model);

        return $this->successResponse(new SolicitudCertificacionResource($model));
    }

    public function legacyManualFlow(): JsonResponse
    {
        return $this->errorResponse(
            'El flujo manual de aprobación fue retirado. Las certificaciones ordinarias se expiden por autoservicio.',
            null,
            410,
            'LEGACY_MANUAL_APPROVAL_DISABLED'
        );
    }
}
