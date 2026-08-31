<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoSolicitudEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Http\Requests\RechazarSolicitudRequest;
use App\Http\Requests\StoreSolicitudCertificacionRequest;
use App\Http\Requests\UpdateEstadoSolicitudRequest;
use App\Http\Resources\SolicitudCertificacionResource;
use App\Models\ParametroSistema;
use App\Models\SolicitudCertificacion;
use App\Services\DisponibilidadCertificacionService;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolicitudCertificacionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
        private readonly DisponibilidadCertificacionService $disponibilidad,
    ) {}

    public function index(IndexQueryRequest $request): JsonResponse
    {
        $this->authorize('viewAny', SolicitudCertificacion::class);

        $estado = $request->filled('estado') ? (string) $request->validated('estado') : null;

        $query = SolicitudCertificacion::with(['funcionario.cargo', 'creadoPor', 'revisadoPor'])
            ->when($estado !== null, fn ($q) => $q->where('estado', $estado))
            ->when($request->filled('tipo_certificado'), fn ($q) => $q->where('tipo_certificado', $request->tipo_certificado))
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->funcionario_id))
            ->when($request->filled('fecha_desde'), fn ($q) => $q->whereDate('created_at', '>=', $request->fecha_desde))
            ->when($request->filled('fecha_hasta'), fn ($q) => $q->whereDate('created_at', '<=', $request->fecha_hasta))
            ->orderBy($request->validated('orden', 'created_at'), $request->validated('direccion', 'desc'));

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

        $requiereSalario = $request->boolean('requiere_salario');
        $periodo = $this->disponibilidad->periodo();
        $modalidad = $requiereSalario ? 'con salario' : 'sin salario';

        $yaExiste = SolicitudCertificacion::query()
            ->where('funcionario_id', $funcionario->id)
            ->whereDate('periodo_mes', $periodo->toDateString())
            ->where('requiere_salario', $requiereSalario)
            ->exists();

        if ($yaExiste) {
            return $this->limiteMensualResponse($modalidad);
        }

        try {
            $solicitud = DB::transaction(function () use ($request, $funcionario, $requiereSalario, $periodo) {
                // Serializa el consecutivo anual del radicado. La cuota mensual se
                // protege definitivamente mediante el UNIQUE de PostgreSQL.
                DB::select('SELECT pg_advisory_xact_lock(?)', [$periodo->year]);

                $solicitud = SolicitudCertificacion::create([
                    'funcionario_id' => $funcionario->id,
                    'tipo_certificado' => $request->validated('tipo_certificado'),
                    'estado' => EstadoSolicitudEnum::Pendiente,
                    'requiere_pago' => false,
                    'requiere_salario' => $requiereSalario,
                    'periodo_mes' => $periodo->toDateString(),
                    'observaciones' => $request->validated('observaciones'),
                    'created_by' => $request->user()->id,
                ]);

                $this->registrarAuditoria->execute(
                    accion: 'crear_solicitud',
                    modelo: 'SolicitudCertificacion',
                    modeloId: $solicitud->id,
                    descripcion: "Solicitud {$solicitud->radicado} creada por funcionario ID {$funcionario->id}.",
                    metadata: [
                        'periodo_mes' => $periodo->toDateString(),
                        'requiere_salario' => $requiereSalario,
                    ],
                );

                return $solicitud;
            });
        } catch (QueryException $exception) {
            if ($this->esColisionMensual($exception)) {
                return $this->limiteMensualResponse($modalidad);
            }

            throw $exception;
        }

        return $this->createdResponse(
            new SolicitudCertificacionResource($solicitud->load('funcionario.cargo')),
            'Solicitud creada correctamente.'
        );
    }

    private function limiteMensualResponse(string $modalidad): JsonResponse
    {
        return $this->errorResponse(
            "Ya existe una solicitud de certificación {$modalidad} para el mes actual.",
            null,
            409,
            'MONTHLY_CERTIFICATE_LIMIT',
        );
    }

    private function esColisionMensual(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505'
            && str_contains((string) ($exception->errorInfo[2] ?? ''), 'solicitudes_funcionario_periodo_modalidad_unique');
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
            'estado' => $nuevoEstado,
            'motivo_rechazo' => $motivoRechazo,
            'observaciones' => $observaciones ?? $solicitudModel->observaciones,
            'requiere_pago' => $requierePago ?? $solicitudModel->requiere_pago,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->registrarAuditoria->execute(
            accion: $accionAuditoria,
            modelo: 'SolicitudCertificacion',
            modeloId: $solicitudModel->id,
            descripcion: "Solicitud {$solicitudModel->radicado} cambio de '{$estadoAnterior}' a '{$nuevoEstado->value}'.",
            metadata: [
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $nuevoEstado->value,
                'motivo_rechazo' => $motivoRechazo,
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
