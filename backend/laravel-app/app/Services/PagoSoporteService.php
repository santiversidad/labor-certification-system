<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PagoSoporteService
{
    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * Conserva de forma segura el histórico de soportes de solicitudes previas
     * a la activación del flujo automático con pasarela.
     */
    public function cargar(
        SolicitudCertificacion $solicitud,
        UploadedFile $archivo,
        User $funcionario,
        ?string $observaciones = null,
    ): PagoSoporte {
        $carpeta = "pagos-soportes/{$solicitud->id}";
        $nombreSeguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', $archivo->getClientOriginalName());
        $rutaNueva = Storage::disk('local')->putFileAs(
            $carpeta,
            $archivo,
            Str::uuid().'_'.$nombreSeguro,
        );

        if (! is_string($rutaNueva)) {
            throw new RuntimeException('No fue posible almacenar el nuevo soporte de pago.');
        }

        $rutaAnterior = null;

        try {
            $pago = DB::transaction(function () use (
                $solicitud,
                $archivo,
                $observaciones,
                $rutaNueva,
                &$rutaAnterior,
            ): PagoSoporte {
                $existente = PagoSoporte::query()
                    ->where('solicitud_certificacion_id', $solicitud->id)
                    ->lockForUpdate()
                    ->first();

                $rutaAnterior = $existente?->archivo_path;
                $datos = [
                    'solicitud_certificacion_id' => $solicitud->id,
                    'funcionario_id' => $solicitud->funcionario_id,
                    'archivo_path' => $rutaNueva,
                    'archivo_original_nombre' => $archivo->getClientOriginalName(),
                    'estado' => EstadoPagoEnum::Cargado,
                    'observaciones' => $observaciones,
                    'validado_por' => null,
                    'validado_at' => null,
                ];

                if ($existente) {
                    $existente->update($datos);
                    $pago = $existente;
                } else {
                    $pago = PagoSoporte::create($datos);
                }

                $solicitud->update(['estado' => EstadoSolicitudEnum::PagoEnRevision]);

                return $pago;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($rutaNueva);
            throw $exception;
        }

        if ($rutaAnterior && $rutaAnterior !== $rutaNueva) {
            try {
                if (Storage::disk('local')->exists($rutaAnterior)
                    && ! Storage::disk('local')->delete($rutaAnterior)) {
                    throw new RuntimeException('El almacenamiento no confirmó la eliminación.');
                }
            } catch (\Throwable $exception) {
                Log::error('No fue posible eliminar el soporte reemplazado.', [
                    'pago_id' => $pago->id,
                    'solicitud_id' => $solicitud->id,
                    'exception' => $exception::class,
                ]);
                $this->registrarAuditoria->execute(
                    accion: 'eliminar_soporte_anterior_fallido',
                    modelo: 'PagoSoporte',
                    modeloId: $pago->id,
                    descripcion: 'El soporte nuevo quedó confirmado, pero el archivo reemplazado no pudo eliminarse.',
                );
            }
        }

        return $pago;
    }

    public function eliminarArchivo(PagoSoporte $pago): void
    {
        if (Storage::disk('local')->exists($pago->archivo_path)) {
            Storage::disk('local')->delete($pago->archivo_path);
        }
    }
}
