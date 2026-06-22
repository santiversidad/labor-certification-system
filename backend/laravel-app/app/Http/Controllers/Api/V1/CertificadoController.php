<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoCertificadoEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnularCertificadoRequest;
use App\Http\Resources\CertificadoResource;
use App\Models\Certificado;
use App\Models\SolicitudCertificacion;
use App\Services\GenerarCertificadoService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CertificadoController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly GenerarCertificadoService $generarCertificadoService,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Certificado::class);

        $certificados = Certificado::with(['generadoPor', 'solicitud', 'funcionario'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            CertificadoResource::collection($certificados)->response()->getData(true),
            'Certificados consultados correctamente.'
        );
    }

    public function generar(Request $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::with(['funcionario.cargo', 'pagoSoporte', 'certificado'])
            ->findOrFail($solicitud);

        $this->authorize('generarCertificado', $solicitudModel);

        $resultado = $this->generarCertificadoService->generar($solicitudModel, $request->user());
        /** @var Certificado $certificado */
        $certificado = $resultado['certificado'];

        $this->registrarAuditoria->execute(
            accion: 'generar_certificado',
            modelo: 'Certificado',
            modeloId: $certificado->id,
            descripcion: "Certificado {$certificado->codigo_unico} generado para solicitud {$solicitudModel->radicado}.",
        );

        return $this->createdResponse([
            'certificado'    => new CertificadoResource($certificado),
            'token'          => $resultado['token'],
            'url_validacion' => $resultado['url_validacion'],
        ], 'Certificado generado correctamente.');
    }

    public function show(Request $request, int $certificado): JsonResponse
    {
        $model = Certificado::with(['generadoPor', 'anuladoPor', 'solicitud', 'funcionario'])
            ->findOrFail($certificado);

        $this->authorize('view', $model);

        return $this->successResponse(
            new CertificadoResource($model),
            'Certificado consultado correctamente.'
        );
    }

    public function descargar(Request $request, int $certificado)
    {
        $model = Certificado::findOrFail($certificado);
        $this->authorize('descargar', $model);

        if (! $model->archivo_pdf_path || ! Storage::disk('local')->exists($model->archivo_pdf_path)) {
            return $this->notFoundResponse('No se encontro el archivo PDF del certificado.');
        }

        if ($model->estado === EstadoCertificadoEnum::Vigente) {
            $model->update(['estado' => EstadoCertificadoEnum::Descargado]);
        }

        $this->registrarAuditoria->execute(
            accion: 'descargar_certificado',
            modelo: 'Certificado',
            modeloId: $model->id,
            descripcion: "Certificado {$model->codigo_unico} descargado.",
        );

        return response()->download(
            Storage::disk('local')->path($model->archivo_pdf_path),
            "{$model->codigo_unico}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function anular(AnularCertificadoRequest $request, int $certificado): JsonResponse
    {
        $model = Certificado::findOrFail($certificado);
        $this->authorize('anular', $model);

        if ($model->estado === EstadoCertificadoEnum::Anulado) {
            return $this->errorResponse('El certificado ya se encuentra anulado.', null, 422);
        }

        $model->update([
            'estado'           => EstadoCertificadoEnum::Anulado,
            'motivo_anulacion' => $request->motivo,
            'anulado_por'      => $request->user()->id,
            'anulado_at'       => now(),
        ]);

        $this->registrarAuditoria->execute(
            accion: 'anular_certificado',
            modelo: 'Certificado',
            modeloId: $model->id,
            descripcion: "Certificado {$model->codigo_unico} anulado.",
            metadata: ['motivo' => $request->motivo],
        );

        return $this->successResponse(
            new CertificadoResource($model->fresh(['anuladoPor', 'generadoPor', 'solicitud', 'funcionario'])),
            'Certificado anulado correctamente.'
        );
    }
}
