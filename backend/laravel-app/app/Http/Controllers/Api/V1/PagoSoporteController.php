<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Http\Requests\ValidarPagoRequest;
use App\Http\Resources\PagoSoporteResource;
use App\Models\PagoSoporte;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** @deprecated Manual receipts/review are historical compatibility only, not autoservice. */
class PagoSoporteController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function index(IndexQueryRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PagoSoporte::class);

        $pagos = PagoSoporte::with(['solicitud', 'funcionario', 'validadoPor'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->funcionario_id))
            ->orderBy($request->validated('orden', 'created_at'), $request->validated('direccion', 'desc'))
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            PagoSoporteResource::collection($pagos)->response()->getData(true),
            'Soportes de pago consultados correctamente.'
        );
    }

    public function show(Request $request, int $pago): JsonResponse
    {
        $pagoModel = PagoSoporte::with(['solicitud', 'funcionario', 'validadoPor'])->findOrFail($pago);
        $this->authorize('view', $pagoModel);

        return $this->successResponse(
            new PagoSoporteResource($pagoModel),
            'Soporte de pago consultado correctamente.'
        );
    }

    public function descargar(Request $request, int $pago)
    {
        $pagoModel = PagoSoporte::findOrFail($pago);
        $this->authorize('descargar', $pagoModel);

        if (! Storage::disk('local')->exists($pagoModel->archivo_path)) {
            return $this->notFoundResponse('No se encontró el archivo del soporte de pago.');
        }

        $nombre = basename($pagoModel->archivo_original_nombre ?: 'soporte-pago');
        $mime = Storage::disk('local')->mimeType($pagoModel->archivo_path)
            ?: 'application/octet-stream';
        $disposition = (new ResponseHeaderBag)->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $nombre,
            'soporte-pago',
        );

        $this->registrarAuditoria->execute(
            accion: 'visualizar_soporte_pago',
            modelo: 'PagoSoporte',
            modeloId: $pagoModel->id,
            descripcion: "Acceso autorizado al soporte de pago ID {$pagoModel->id}.",
        );

        return response()->file(
            Storage::disk('local')->path($pagoModel->archivo_path),
            [
                'Content-Type' => $mime,
                'Content-Disposition' => $disposition,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function cargar(Request $request, int $solicitud): JsonResponse
    {
        return $this->errorResponse('Utilice la carga versionada de comprobantes de pago.', null, 410, 'LEGACY_PAYMENT_UPLOAD_DISABLED');
    }

    public function validar(ValidarPagoRequest $request, int $pago): JsonResponse
    {
        $model = PagoSoporte::findOrFail($pago);
        $this->authorize('validar', $model);

        return $this->errorResponse('Utilice la validación de pagos de Secretaría.', null, 410, 'LEGACY_PAYMENT_REVIEW_DISABLED');
    }

    public function rechazar(ValidarPagoRequest $request, int $pago): JsonResponse
    {
        $model = PagoSoporte::findOrFail($pago);
        $this->authorize('rechazar', $model);

        return $this->errorResponse('Utilice la validación de pagos de Secretaría.', null, 410, 'LEGACY_PAYMENT_REVIEW_DISABLED');
    }
}
