<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoSolicitudEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarEstadoSolicitudRequest;
use App\Http\Requests\StoreSolicitudCertificacionRequest;
use App\Http\Resources\SolicitudCertificacionResource;
use App\Models\ParametroSistema;
use App\Models\SolicitudCertificacion;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SolicitudCertificacionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * GET /api/v1/solicitudes
     *
     * Admin y secretario: ven todas las solicitudes con filtros.
     * Funcionario: solo ve las propias.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SolicitudCertificacion::class);

        $query = SolicitudCertificacion::with(['funcionario', 'creadoPor', 'revisadoPor'])
            ->when(
                $request->user()->hasRole('funcionario'),
                // Funcionario solo ve las suyas
                fn ($q) => $q->where('funcionario_id', $request->user()->funcionario?->id)
            )
            ->when($request->filled('estado'),
                fn ($q) => $q->where('estado', $request->estado)
            )
            ->when($request->filled('tipo_certificado'),
                fn ($q) => $q->where('tipo_certificado', $request->tipo_certificado)
            )
            ->when($request->filled('funcionario_id'),
                fn ($q) => $q->where('funcionario_id', $request->funcionario_id)
            )
            ->latest();

        $solicitudes = $query->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            SolicitudCertificacionResource::collection($solicitudes)->response()->getData(true),
            'Solicitudes consultadas correctamente.'
        );
    }

    /**
     * POST /api/v1/solicitudes
     *
     * Un funcionario crea una nueva solicitud para sí mismo.
     * El estado inicial es 'pendiente'.
     */
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
            'requiere_pago'    => false,   // El admin/secretario lo define al revisar
            'requiere_salario' => $request->boolean('requiere_salario', false),
            'observaciones'    => $request->observaciones,
            'created_by'       => $request->user()->id,
        ]);

        $this->registrarAuditoria->execute(
            accion: 'crear',
            modelo: 'SolicitudCertificacion',
            modeloId: $solicitud->id,
            descripcion: "Solicitud de certificado '{$solicitud->tipo_certificado->value}' creada por funcionario ID {$funcionario->id}.",
        );

        return $this->createdResponse(
            new SolicitudCertificacionResource($solicitud->load('funcionario')),
            'Solicitud creada correctamente. Quedó en estado pendiente.'
        );
    }

    /**
     * GET /api/v1/solicitudes/{solicitud}
     *
     * Retorna el detalle completo de una solicitud con todas sus relaciones.
     */
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

    /**
     * PATCH /api/v1/solicitudes/{solicitud}/estado
     *
     * Endpoint genérico de transición de estado.
     * Transiciones válidas:
     *   pendiente      → en_revision | rechazado | cancelado
     *   en_revision    → requiere_pago | aprobado | rechazado
     *   requiere_pago  → cancelado  (soporte → pago_pendiente via PagoSoporteController)
     *   pago_pendiente → pago_validado | rechazado
     *   pago_validado  → aprobado
     *   aprobado       → cancelado  (generado via CertificadoController)
     */
    public function cambiarEstado(CambiarEstadoSolicitudRequest $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('cambiarEstado', $solicitudModel);

        return $this->procesarTransicion(
            request:        $request,
            solicitudModel: $solicitudModel,
            nuevoEstado:    EstadoSolicitudEnum::from($request->estado),
            motivoRechazo:  $request->motivo_rechazo,
            observaciones:  $request->filled('observaciones') ? $request->observaciones : null,
            requierePago:   $request->filled('requiere_pago') ? $request->boolean('requiere_pago') : null,
        );
    }

    /**
     * PATCH /api/v1/solicitudes/{solicitud}/aprobar
     *
     * Alias explícito para aprobar una solicitud.
     * La solicitud debe estar en un estado que permita ir a 'aprobado'.
     */
    public function aprobar(Request $request, int $solicitud): JsonResponse
    {
        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('cambiarEstado', $solicitudModel);

        return $this->procesarTransicion(
            request:        $request,
            solicitudModel: $solicitudModel,
            nuevoEstado:    EstadoSolicitudEnum::Aprobado,
        );
    }

    /**
     * PATCH /api/v1/solicitudes/{solicitud}/rechazar
     *
     * Alias explícito para rechazar. Exige motivo_rechazo.
     */
    public function rechazar(Request $request, int $solicitud): JsonResponse
    {
        $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ], [
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
        ]);

        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('cambiarEstado', $solicitudModel);

        return $this->procesarTransicion(
            request:        $request,
            solicitudModel: $solicitudModel,
            nuevoEstado:    EstadoSolicitudEnum::Rechazado,
            motivoRechazo:  $request->motivo_rechazo,
        );
    }

    /**
     * PATCH /api/v1/solicitudes/{solicitud}/marcar-pago
     *
     * Alias explícito para marcar que la solicitud requiere pago.
     * Bloqueado si el módulo de pagos está desactivado globalmente.
     */
    public function marcarPago(Request $request, int $solicitud): JsonResponse
    {
        if (! ParametroSistema::valor('requiere_pago_certificado', false)) {
            return $this->errorResponse(
                'El módulo de pagos está desactivado. No se puede marcar la solicitud como pendiente de pago.',
                null,
                422
            );
        }

        $solicitudModel = SolicitudCertificacion::findOrFail($solicitud);
        $this->authorize('cambiarEstado', $solicitudModel);

        return $this->procesarTransicion(
            request:        $request,
            solicitudModel: $solicitudModel,
            nuevoEstado:    EstadoSolicitudEnum::RequierePago,
            requierePago:   true,
        );
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    /**
     * Ejecuta el cambio de estado validando la transición, persistiendo y auditando.
     * Compartido por cambiarEstado() y todos los alias methods.
     */
    private function procesarTransicion(
        Request                 $request,
        SolicitudCertificacion  $solicitudModel,
        EstadoSolicitudEnum     $nuevoEstado,
        ?string                 $motivoRechazo = null,
        ?string                 $observaciones = null,
        ?bool                   $requierePago  = null,
    ): JsonResponse {
        if (! $this->transicionValida($solicitudModel->estado, $nuevoEstado)) {
            return $this->errorResponse(
                "No es posible pasar de '{$solicitudModel->estado->value}' a '{$nuevoEstado->value}'.",
                null,
                422
            );
        }

        $solicitudModel->update([
            'estado'         => $nuevoEstado,
            'motivo_rechazo' => $motivoRechazo,
            'observaciones'  => $observaciones ?? $solicitudModel->observaciones,
            'requiere_pago'  => $requierePago  ?? $solicitudModel->requiere_pago,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ]);

        $this->registrarAuditoria->execute(
            accion:      'cambiar_estado',
            modelo:      'SolicitudCertificacion',
            modeloId:    $solicitudModel->id,
            descripcion: "Estado cambiado a '{$nuevoEstado->value}' por usuario ID {$request->user()->id}.",
            metadata: [
                'estado_anterior' => $solicitudModel->getOriginal('estado'),
                'estado_nuevo'    => $nuevoEstado->value,
                'motivo_rechazo'  => $motivoRechazo,
            ],
        );

        return $this->successResponse(
            new SolicitudCertificacionResource($solicitudModel->fresh('funcionario', 'revisadoPor')),
            "Estado actualizado a '{$nuevoEstado->label()}'."
        );
    }

    /**
     * Define las transiciones de estado permitidas.
     * Cada clave es el estado actual; el valor es el array de destinos válidos.
     */
    private function transicionValida(EstadoSolicitudEnum $actual, EstadoSolicitudEnum $nuevo): bool
    {
        $mapa = [
            EstadoSolicitudEnum::Pendiente->value     => [
                EstadoSolicitudEnum::EnRevision->value,
                EstadoSolicitudEnum::Rechazado->value,
                EstadoSolicitudEnum::Cancelado->value,
            ],
            EstadoSolicitudEnum::EnRevision->value    => [
                EstadoSolicitudEnum::RequierePago->value,
                EstadoSolicitudEnum::Aprobado->value,
                EstadoSolicitudEnum::Rechazado->value,
            ],
            EstadoSolicitudEnum::RequierePago->value  => [
                EstadoSolicitudEnum::Cancelado->value,
            ],
            EstadoSolicitudEnum::PagoPendiente->value => [
                EstadoSolicitudEnum::PagoValidado->value,
                EstadoSolicitudEnum::Rechazado->value,
            ],
            EstadoSolicitudEnum::PagoValidado->value  => [
                EstadoSolicitudEnum::Aprobado->value,
            ],
            EstadoSolicitudEnum::Aprobado->value      => [
                EstadoSolicitudEnum::Cancelado->value,
            ],
        ];

        return in_array($nuevo->value, $mapa[$actual->value] ?? []);
    }
}
