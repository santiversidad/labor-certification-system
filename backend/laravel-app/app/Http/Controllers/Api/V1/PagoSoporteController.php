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
        private readonly PagoSoporteService $pagoSoporteService,
        private readonly ValidarPagoAction $validarPagoAction,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PagoSoporte::class);

        $pagos = PagoSoporte::with(['solicitud', 'funcionario', 'validadoPor'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->funcionario_id))
            ->latest()
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

    public function cargar(StorePagoSoporteRequest $request, int $solicitud): JsonResponse
    {
        if (! ParametroSistema::valor('requiere_pago_certificado', false)) {
            return $this->errorResponse(
                'El modulo de pagos esta desactivado. No se requiere soporte de pago en este momento.',
                null,
                422
            );
        }

        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);

        if ($request->user()->funcionario?->id !== $solicitudModel->funcionario_id) {
            return $this->forbiddenResponse('Solo el funcionario titular puede cargar el soporte de pago.');
        }

        if (! $solicitudModel->requiere_pago || $solicitudModel->estado !== EstadoSolicitudEnum::PendientePago) {
            return $this->errorResponse(
                "La solicitud debe estar en estado 'pendiente_pago' y requerir pago para cargar un soporte.",
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
            accion: 'cargar_soporte_pago',
            modelo: 'PagoSoporte',
            modeloId: $pago->id,
            descripcion: "Soporte de pago cargado para solicitud {$solicitudModel->radicado}.",
        );

        return $this->createdResponse(
            new PagoSoporteResource($pago->load('solicitud', 'funcionario')),
            'Soporte de pago cargado correctamente. Quedo en revision.'
        );
    }

    public function validar(ValidarPagoRequest $request, int $pago): JsonResponse
    {
        $pagoModel = PagoSoporte::with('solicitud')->findOrFail($pago);
        $this->authorize('validar', $pagoModel);

        if (! in_array($pagoModel->estado, [EstadoPagoEnum::Pendiente, EstadoPagoEnum::Cargado], true)) {
            return $this->errorResponse(
                "Solo se pueden aprobar soportes pendientes o cargados. Estado actual: '{$pagoModel->estado->value}'.",
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
            accion: 'aprobar_pago',
            modelo: 'PagoSoporte',
            modeloId: $pagoModel->id,
            descripcion: "Soporte de pago ID {$pago} aprobado.",
        );

        return $this->successResponse(
            new PagoSoporteResource($pagoModel->fresh('validadoPor', 'solicitud', 'funcionario')),
            'Pago aprobado correctamente. La solicitud quedo aprobada.'
        );
    }

    public function rechazar(ValidarPagoRequest $request, int $pago): JsonResponse
    {
        $pagoModel = PagoSoporte::with('solicitud')->findOrFail($pago);
        $this->authorize('rechazar', $pagoModel);

        if (! in_array($pagoModel->estado, [EstadoPagoEnum::Pendiente, EstadoPagoEnum::Cargado], true)) {
            return $this->errorResponse(
                "Solo se pueden rechazar soportes pendientes o cargados. Estado actual: '{$pagoModel->estado->value}'.",
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
            descripcion: "Soporte de pago ID {$pago} rechazado.",
        );

        return $this->successResponse(
            new PagoSoporteResource($pagoModel->fresh('validadoPor', 'solicitud', 'funcionario')),
            'Soporte rechazado. La solicitud regreso a pendiente_pago.'
        );
    }
}
