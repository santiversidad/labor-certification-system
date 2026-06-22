<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoSolicitudEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\RechazarSolicitudRequest;
use App\Http\Requests\StoreSolicitudCertificacionRequest;
use App\Http\Requests\UpdateEstadoSolicitudRequest;
use App\Http\Resources\SolicitudCertificacionResource;
use App\Models\ParametroSistema;
use App\Models\SolicitudCertificacion;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudCertificacionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SolicitudCertificacion::class);

        $estado = null;
        if ($request->filled('estado')) {
            try {
                $estado = EstadoSolicitudEnum::fromInput((string) $request->estado)->value;
            } catch (\ValueError) {
                return $this->errorResponse('El estado indicado no es valido.', ['estado' => ['Estado no valido.']], 422);
            }
        }

        $query = SolicitudCertificacion::with(['funcionario.cargo', 'creadoPor', 'revisadoPor'])
            ->when($estado !== null, fn ($q) => $q->where('estado', $estado))
            ->when($request->filled('tipo_certificado'), fn ($q) => $q->where('tipo_certificado', $request->tipo_certificado))
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->funcionario_id))
            ->latest();

        $solicitudes = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            SolicitudCertificacionResource::collection($solicitudes)->response()->getData(true),
            'Solicitudes consultadas correctamente.'
        );
    }

    public function store(StoreSolicitudCertificacionRequest $request): JsonResponse
    {
        $this->authorize('create', SolicitudCertificacion::class);

        $funcionario = $request->user()->funcionario;

        if (! $funcionario) {
            return $this->errorResponse(
                'Su usuario no tiene un registro de funcionario asociado. Contacte al administrador.',
                null,
                422
            );
        }

        if (SolicitudCertificacion::tieneActivaPara($funcionario->id)) {
            return $this->errorResponse(
                'Ya tiene una solicitud en proceso. Debe esperar a que sea resuelta antes de crear una nueva.',
                null,
                422
            );
        }

        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $funcionario->id,
            'tipo_certificado' => $request->tipo_certificado,
            'estado'           => EstadoSolicitudEnum::Pendiente,
            'requiere_pago'    => false,
            'requiere_salario' => $request->boolean('requiere_salario', false),
            'observaciones'    => $request->observaciones,
            'created_by'       => $request->user()->id,
        ]);

        $this->registrarAuditoria->execute(
            accion: 'crear_solicitud',
            modelo: 'SolicitudCertificacion',
            modeloId: $solicitud->id,
            descripcion: "Solicitud {$solicitud->radicado} creada por funcionario ID {$funcionario->id}.",
        );

        return $this->createdResponse(
            new SolicitudCertificacionResource($solicitud->load('funcionario.cargo')),
            'Solicitud creada correctamente.'
        );
    }

    public function show(Request $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::with([
            'funcionario.cargo',
            'creadoPor',
            'revisadoPor',
            'pagoSoporte.validadoPor',
            'certificado.generadoPor',
        ])->findOrFail($solicitud);

        $this->authorize('view', $solicitudModel);

        return $this->successResponse(
            new SolicitudCertificacionResource($solicitudModel),
            'Solicitud consultada correctamente.'
        );
    }

    public function cambiarEstado(UpdateEstadoSolicitudRequest $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('cambiarEstado', $solicitudModel);

        $nuevoEstado = EstadoSolicitudEnum::from($request->estado);

        if ($nuevoEstado === EstadoSolicitudEnum::PendientePago) {
            return $this->marcarPago($request, $solicitud);
        }

        if ($nuevoEstado === EstadoSolicitudEnum::CertificadoGenerado) {
            return $this->errorResponse(
                'Use el endpoint de generacion de certificados para generar el certificado.',
                null,
                422
            );
        }

        return $this->procesarTransicion(
            request: $request,
            solicitudModel: $solicitudModel,
            nuevoEstado: $nuevoEstado,
            motivoRechazo: $request->motivo_rechazo,
            observaciones: $request->filled('observaciones') ? $request->observaciones : null,
            requierePago: $request->filled('requiere_pago') ? $request->boolean('requiere_pago') : null,
            accionAuditoria: 'cambiar_estado_solicitud',
        );
    }

    public function aprobar(Request $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('aprobar', $solicitudModel);

        return $this->procesarTransicion(
            request: $request,
            solicitudModel: $solicitudModel,
            nuevoEstado: EstadoSolicitudEnum::Aprobada,
            accionAuditoria: 'aprobar_solicitud',
        );
    }

    public function rechazar(RechazarSolicitudRequest $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('rechazar', $solicitudModel);

        return $this->procesarTransicion(
            request: $request,
            solicitudModel: $solicitudModel,
            nuevoEstado: EstadoSolicitudEnum::Rechazada,
            motivoRechazo: $request->motivo_rechazo,
            accionAuditoria: 'rechazar_solicitud',
        );
    }

    public function marcarPago(Request $request, int $solicitud): JsonResponse
    {
        if (! ParametroSistema::valor('requiere_pago_certificado', false)) {
            return $this->errorResponse(
                'El modulo de pagos esta desactivado. No se puede marcar la solicitud como pendiente de pago.',
                null,
                422
            );
        }

        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('cambiarEstado', $solicitudModel);

        return $this->procesarTransicion(
            request: $request,
            solicitudModel: $solicitudModel,
            nuevoEstado: EstadoSolicitudEnum::PendientePago,
            requierePago: true,
            accionAuditoria: 'marcar_pendiente_pago',
        );
    }

    private function procesarTransicion(
        Request $request,
        SolicitudCertificacion $solicitudModel,
        EstadoSolicitudEnum $nuevoEstado,
        ?string $motivoRechazo = null,
        ?string $observaciones = null,
        ?bool $requierePago = null,
        string $accionAuditoria = 'cambiar_estado_solicitud',
    ): JsonResponse {
        if (! $this->transicionValida($solicitudModel->estado, $nuevoEstado)) {
            return $this->errorResponse(
                "No es posible pasar de '{$solicitudModel->estado->value}' a '{$nuevoEstado->value}'.",
                null,
                422
            );
        }

        $estadoAnterior = $solicitudModel->estado->value;

        $solicitudModel->update([
            'estado'         => $nuevoEstado,
            'motivo_rechazo' => $motivoRechazo,
            'observaciones'  => $observaciones ?? $solicitudModel->observaciones,
            'requiere_pago'  => $requierePago ?? $solicitudModel->requiere_pago,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ]);

        $this->registrarAuditoria->execute(
            accion: $accionAuditoria,
            modelo: 'SolicitudCertificacion',
            modeloId: $solicitudModel->id,
            descripcion: "Solicitud {$solicitudModel->radicado} cambio de '{$estadoAnterior}' a '{$nuevoEstado->value}'.",
            metadata: [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo'    => $nuevoEstado->value,
                'motivo_rechazo'  => $motivoRechazo,
            ],
        );

        return $this->successResponse(
            new SolicitudCertificacionResource($solicitudModel->fresh('funcionario.cargo', 'revisadoPor', 'pagoSoporte')),
            "Estado actualizado a '{$nuevoEstado->label()}'."
        );
    }

    private function transicionValida(EstadoSolicitudEnum $actual, EstadoSolicitudEnum $nuevo): bool
    {
        $mapa = [
            EstadoSolicitudEnum::Pendiente->value => [
                EstadoSolicitudEnum::EnRevision->value,
                EstadoSolicitudEnum::PendientePago->value,
                EstadoSolicitudEnum::Aprobada->value,
                EstadoSolicitudEnum::Rechazada->value,
            ],
            EstadoSolicitudEnum::EnRevision->value => [
                EstadoSolicitudEnum::PendientePago->value,
                EstadoSolicitudEnum::Aprobada->value,
                EstadoSolicitudEnum::Rechazada->value,
            ],
            EstadoSolicitudEnum::PendientePago->value => [
                EstadoSolicitudEnum::PagoEnRevision->value,
                EstadoSolicitudEnum::Rechazada->value,
                EstadoSolicitudEnum::Cerrada->value,
            ],
            EstadoSolicitudEnum::PagoEnRevision->value => [
                EstadoSolicitudEnum::Aprobada->value,
                EstadoSolicitudEnum::PendientePago->value,
                EstadoSolicitudEnum::Rechazada->value,
            ],
            EstadoSolicitudEnum::Aprobada->value => [
                EstadoSolicitudEnum::Cerrada->value,
            ],
        ];

        return in_array($nuevo->value, $mapa[$actual->value] ?? [], true);
    }
}
