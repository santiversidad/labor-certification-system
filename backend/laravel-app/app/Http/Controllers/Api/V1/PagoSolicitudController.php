<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\PagoComprobanteResource;
use App\Models\PagoComprobante;
use App\Models\SolicitudCertificacion;
use App\Services\PagoSolicitudService;
use App\Traits\ApiResponse;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PagoSolicitudController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PagoSolicitudService $pagos) {}

    public function index(Request $request)
    {
        $this->autorizarSecretaria($request, 'pagos.ver');
        $datos = $request->validate([
            'estado' => ['sometimes', Rule::in(['pendiente_pago', 'pago_en_revision', 'pago_rechazado', 'pago_confirmado', 'generada', 'expedicion_fallida'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $filas = SolicitudCertificacion::with(['funcionario', 'pagoSoporte.comprobantes.revisor', 'ordenPago'])
            ->where('requiere_pago', true)->where('estado', $datos['estado'] ?? 'pago_en_revision')
            ->orderBy('created_at')->paginate($datos['per_page'] ?? 15);

        return $this->successResponse(['data' => $filas->getCollection()->map(fn ($s) => $this->payload($s)),
            'meta' => ['current_page' => $filas->currentPage(), 'last_page' => $filas->lastPage(), 'total' => $filas->total()]]);
    }

    public function show(Request $request, int $solicitud)
    {
        $this->autorizarSecretaria($request, 'pagos.ver');
        $model = SolicitudCertificacion::findOrFail($solicitud);
        abort_unless($model->requiere_pago, 404);

        return $this->successResponse($this->payload($model));
    }

    public function cargar(Request $request, int $solicitud)
    {
        $model = SolicitudCertificacion::findOrFail($solicitud);
        abort_unless($request->user()->hasRole('funcionario') && $request->user()->can('pagos.cargar')
            && $request->user()->funcionario?->id === $model->funcionario_id, 403);
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:'.config('payments.receipt_max_kb')],
            'referencia' => ['nullable', 'string', 'max:150'], 'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $model = $this->pagos->cargar($solicitud, $request->user(), $request->file('archivo'), $datos['referencia'] ?? null, $datos['observaciones'] ?? null);
        } catch (DomainException $error) {
            return $this->errorResponse('La solicitud no permite adjuntar un comprobante en este estado.', null, 409, $error->getMessage());
        } catch (\RuntimeException $error) {
            report($error);

            return $this->errorResponse('No fue posible guardar el comprobante. Intente nuevamente.', null, 503, 'COMPROBANTE_STORAGE_FAILED');
        }

        return $this->createdResponse($this->payload($model), 'Comprobante recibido. Pago en revisión.');
    }

    public function confirmar(Request $request, int $solicitud)
    {
        return $this->revisar($request, $solicitud, true);
    }

    public function rechazar(Request $request, int $solicitud)
    {
        return $this->revisar($request, $solicitud, false);
    }

    private function revisar(Request $request, int $solicitud, bool $confirmar)
    {
        $this->autorizarSecretaria($request, $confirmar ? 'pagos.validar' : 'pagos.rechazar');
        $datos = $request->validate(['motivo' => [$confirmar ? 'nullable' : 'required', 'string', 'max:1000']]);
        try {
            $model = $this->pagos->revisar($solicitud, $request->user(), $confirmar, $datos['motivo'] ?? null);
        } catch (DomainException $error) {
            return $this->errorResponse('El pago no permite esta transición. Actualice la solicitud.', null, 409, $error->getMessage());
        }

        return $this->successResponse($this->payload($model), $confirmar ? 'Pago confirmado. Consulte el estado de expedición.' : 'Pago rechazado. El titular puede adjuntar otra evidencia.');
    }

    public function archivo(Request $request, int $solicitud, int $comprobante)
    {
        $model = SolicitudCertificacion::findOrFail($solicitud);
        $actor = $request->user();
        $titular = $actor->hasRole('funcionario') && $actor->can('pagos.cargar') && $actor->funcionario?->id === $model->funcionario_id;
        abort_unless($titular || (! $actor->hasRole('funcionario') && $actor->can('pagos.ver')), 403);
        $evidencia = PagoComprobante::whereKey($comprobante)
            ->whereHas('soporte', fn ($q) => $q->where('solicitud_certificacion_id', $solicitud))->firstOrFail();
        abort_unless(Storage::disk('local')->exists($evidencia->archivo_path), 404);
        if (! hash_equals($evidencia->sha256, hash_file('sha256', Storage::disk('local')->path($evidencia->archivo_path)))) {
            return $this->errorResponse('El comprobante no pudo verificarse.', null, 409, 'COMPROBANTE_INTEGRIDAD_INVALIDA');
        }
        app(RegistrarAuditoriaAction::class)->execute(accion: 'comprobante_consultado', modelo: 'PagoComprobante', modeloId: $comprobante, actorId: $actor->id);

        return Storage::disk('local')->download($evidencia->archivo_path, 'comprobante-'.$evidencia->id.'.'.pathinfo($evidencia->archivo_path, PATHINFO_EXTENSION),
            ['Content-Type' => $evidencia->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function autorizarSecretaria(Request $request, string $permiso): void
    {
        abort_unless($request->user()->hasRole('secretario') && ! $request->user()->hasRole('funcionario') && $request->user()->can($permiso), 403);
    }

    private function payload(SolicitudCertificacion $s): array
    {
        $s->loadMissing(['funcionario', 'pagoSoporte.comprobantes.revisor', 'ordenPago']);

        return [
            'id' => $s->id, 'radicado' => $s->radicado, 'tipo_certificado' => $s->tipo_certificado->value,
            'estado' => $s->estado->value, 'requiere_pago' => $s->requiere_pago, 'created_at' => $s->created_at?->toISOString(),
            'funcionario' => ['id' => $s->funcionario_id, 'nombres' => $s->funcionario->nombres, 'apellidos' => $s->funcionario->apellidos,
                'documento_parcial' => '••••'.substr($s->funcionario->numero_documento, -4)],
            'motivo_rechazo' => $s->motivo_rechazo, 'error_expedicion' => $s->error_expedicion,
            'referencia_interna' => $s->ordenPago?->referencia, 'monto' => $s->ordenPago?->monto,
            'comprobantes' => PagoComprobanteResource::collection($s->pagoSoporte?->comprobantes ?? collect()),
            'comprobante_max_kb' => config('payments.receipt_max_kb'),
        ];
    }
}
