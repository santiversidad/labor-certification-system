<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Actions\ValidarPagoAction;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePagoSoporteRequest;
use App\Http\Requests\ValidarPagoRequest;
use App\Http\Resources\PagoSoporteResource;
use App\Models\PagoSoporte;
use App\Models\ParametroSistema;
use App\Models\SolicitudCertificacion;
use App\Services\PagoSoporteService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoSoporteController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PagoSoporteService    $pagoSoporteService,
        private readonly ValidarPagoAction     $validarPagoAction,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * GET /api/v1/pagos/{pago}
     *
     * Detalle de un soporte de pago.
     * Admin/secretario: cualquier soporte.
     * Funcionario: solo el propio.
     */
    public function show(Request $request, int $pago): JsonResponse
    {
        if (! $request->user()->can('pagos.ver')) {
            return $this->forbiddenResponse('No tiene permisos para consultar soportes de pago.');
        }

        $pagoModel = PagoSoporte::with(['solicitud', 'funcionario', 'validadoPor'])->findOrFail($pago);

        if ($request->user()->hasRole('funcionario')
            && $pagoModel->funcionario_id !== $request->user()->funcionario?->id) {
            return $this->forbiddenResponse('No tiene permisos para ver este soporte de pago.');
        }

        return $this->successResponse(
            new PagoSoporteResource($pagoModel),
            'Soporte de pago consultado correctamente.'
        );
    }

    /**
     * POST /api/v1/solicitudes/{solicitud}/soporte-pago
     *
     * El funcionario sube el comprobante de pago para una solicitud
     * que está en estado 'requiere_pago'.
     */
    public function cargar(StorePagoSoporteRequest $request, int $solicitud): JsonResponse
    {
        // Bloquear si el módulo de pagos está desactivado globalmente
        if (! ParametroSistema::valor('requiere_pago_certificado', false)) {
            return $this->errorResponse(
                'El módulo de pagos está desactivado. No se requiere soporte de pago en este momento.',
                null,
                422
            );
        }

        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);

        // Solo el funcionario dueño de la solicitud puede subir el soporte
        if ($request->user()->funcionario?->id !== $solicitudModel->funcionario_id) {
            return $this->forbiddenResponse('Solo el funcionario titular puede cargar el soporte de pago.');
        }

        // La solicitud debe estar en estado 'requiere_pago'
        if ($solicitudModel->estado !== EstadoSolicitudEnum::RequierePago) {
            return $this->errorResponse(
                "La solicitud debe estar en estado 'requiere_pago' para cargar un soporte. Estado actual: '{$solicitudModel->estado->value}'.",
                null,
                422
            );
        }

        $pago = $this->pagoSoporteService->cargar(
            solicitud: $solicitudModel,
            archivo: $request->file('archivo'),
            funcionario: $request->user(),
            observaciones: $request->observaciones,
        );

        $this->registrarAuditoria->execute(
            accion: 'cargar_soporte',
            modelo: 'PagoSoporte',
            modeloId: $pago->id,
            descripcion: "Soporte de pago cargado para solicitud ID {$solicitud} por funcionario ID {$solicitudModel->funcionario_id}.",
        );

        return $this->createdResponse(
            new PagoSoporteResource($pago),
            'Soporte de pago cargado correctamente. Quedó en revisión.'
        );
    }

    /**
     * GET /api/v1/pagos
     *
     * Lista todos los soportes de pago (admin/secretario).
     * Permite filtrar por estado.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('pagos.ver')) {
            return $this->forbiddenResponse();
        }

        $pagos = PagoSoporte::with(['solicitud', 'funcionario', 'validadoPor'])
            ->when($request->filled('estado'),
                fn ($q) => $q->where('estado', $request->estado)
            )
            ->when($request->filled('funcionario_id'),
                fn ($q) => $q->where('funcionario_id', $request->funcionario_id)
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            PagoSoporteResource::collection($pagos)->response()->getData(true),
            'Soportes de pago consultados correctamente.'
        );
    }

    /**
     * PATCH /api/v1/pagos/{pago}/validar
     *
     * Secretario o admin aprueba el soporte de pago.
     * La solicitud avanza a 'pago_validado'.
     */
    public function validar(ValidarPagoRequest $request, int $pago): JsonResponse
    {
        if (! $request->user()->can('pagos.validar')) {
            return $this->forbiddenResponse('No tiene permisos para validar pagos.');
        }

        $pagoModel = PagoSoporte::with('solicitud')->findOrFail($pago);

        if ($pagoModel->estado !== EstadoPagoEnum::Pendiente) {
            return $this->errorResponse(
                "Solo se pueden validar soportes en estado 'pendiente'. Estado actual: '{$pagoModel->estado->value}'.",
                null,
                422
            );
        }

        $this->validarPagoAction->aprobar(
            pago: $pagoModel,
            validador: $request->user(),
            observaciones: $request->observaciones,
        );

        $this->registrarAuditoria->execute(
            accion: 'validar_pago',
            modelo: 'PagoSoporte',
            modeloId: $pagoModel->id,
            descripcion: "Soporte de pago ID {$pago} aprobado por usuario ID {$request->user()->id}.",
        );

        return $this->successResponse(
            new PagoSoporteResource($pagoModel->fresh('validadoPor', 'solicitud')),
            'Pago aprobado correctamente. La solicitud avanzó a pago validado.'
        );
    }

    /**
     * PATCH /api/v1/pagos/{pago}/rechazar
     *
     * Secretario o admin rechaza el soporte de pago.
     * La solicitud regresa a 'requiere_pago' para que el funcionario
     * pueda subir un nuevo soporte corregido.
     */
    public function rechazar(ValidarPagoRequest $request, int $pago): JsonResponse
    {
        if (! $request->user()->can('pagos.rechazar')) {
            return $this->forbiddenResponse('No tiene permisos para rechazar pagos.');
        }

        $pagoModel = PagoSoporte::with('solicitud')->findOrFail($pago);

        if ($pagoModel->estado !== EstadoPagoEnum::Pendiente) {
            return $this->errorResponse(
                "Solo se pueden rechazar soportes en estado 'pendiente'. Estado actual: '{$pagoModel->estado->value}'.",
                null,
                422
            );
        }

        if (! $request->filled('observaciones')) {
            return $this->errorResponse(
                'Debe indicar el motivo del rechazo en el campo observaciones.',
                ['observaciones' => ['El motivo del rechazo es obligatorio.']],
                422
            );
        }

        $this->validarPagoAction->rechazar(
            pago: $pagoModel,
            validador: $request->user(),
            observaciones: $request->observaciones,
        );

        $this->registrarAuditoria->execute(
            accion: 'rechazar_pago',
            modelo: 'PagoSoporte',
            modeloId: $pagoModel->id,
            descripcion: "Soporte de pago ID {$pago} rechazado. Motivo: {$request->observaciones}",
        );

        return $this->successResponse(
            new PagoSoporteResource($pagoModel->fresh('validadoPor', 'solicitud')),
            'Soporte rechazado. La solicitud regresó a estado requiere_pago.'
        );
    }
}
