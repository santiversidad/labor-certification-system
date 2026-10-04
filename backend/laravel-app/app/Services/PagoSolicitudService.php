<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoOrdenPagoEnum;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PagoSolicitudService
{
    public function __construct(
        private readonly RegistrarAuditoriaAction $auditoria,
        private readonly GenerarCertificadoService $generar,
    ) {}

    public function cargar(int $id, User $actor, UploadedFile $archivo, ?string $referencia, ?string $observacion): SolicitudCertificacion
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo->getRealPath());
        $extensiones = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
        if (! $archivo->isValid() || ! isset($extensiones[$mime])
            || ! in_array(strtolower($archivo->getClientOriginalExtension()), $extensiones[$mime], true)
            || $archivo->getSize() > config('payments.receipt_max_kb') * 1024) {
            throw ValidationException::withMessages(['archivo' => ['Adjunte un PDF, JPG/JPEG o PNG válido dentro del tamaño permitido.']]);
        }
        $ruta = null;
        try {
            return DB::transaction(function () use ($id, $actor, $archivo, $referencia, $observacion, $mime, &$ruta) {
                $solicitud = SolicitudCertificacion::whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->hasRole('funcionario') && $actor->can('pagos.cargar')
                    && $actor->funcionario?->id === $solicitud->funcionario_id, 403);
                if (! $solicitud->requiere_pago || ! in_array($solicitud->estado, [EstadoSolicitudEnum::PendientePago, EstadoSolicitudEnum::PagoRechazado], true)) {
                    throw new DomainException('TRANSICION_PAGO_INVALIDA');
                }
                $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$mime];
                $ruta = Storage::disk('local')->putFileAs("pago-comprobantes/{$id}", $archivo, Str::uuid().'.'.$extension);
                if (! is_string($ruta)) {
                    throw new \RuntimeException('COMPROBANTE_STORAGE_FAILED');
                }
                $hash = hash_file('sha256', Storage::disk('local')->path($ruta));
                $pago = PagoSoporte::firstOrNew(['solicitud_certificacion_id' => $id]);
                // Historical aggregate evidence is preserved before any replacement.
                if ($pago->exists && ! $pago->comprobantes()->exists()) {
                    throw new DomainException('COMPROBANTE_LEGACY_REQUIERE_MIGRACION');
                }
                $pago->fill([
                    'funcionario_id' => $solicitud->funcionario_id, 'archivo_path' => $ruta,
                    'archivo_original_nombre' => basename(str_replace('\\', '/', $archivo->getClientOriginalName())),
                    'estado' => EstadoPagoEnum::Cargado, 'observaciones' => $observacion,
                    'validado_por' => null, 'validado_at' => null,
                ])->save();
                $version = (int) $pago->comprobantes()->max('version') + 1;
                $evidencia = $pago->comprobantes()->create([
                    'version' => $version, 'archivo_path' => $ruta, 'nombre_original' => $pago->archivo_original_nombre,
                    'mime_type' => $mime, 'size_bytes' => $archivo->getSize(), 'sha256' => $hash,
                    'referencia' => $referencia, 'observacion_funcionario' => $observacion, 'estado' => 'en_revision',
                ]);
                $solicitud->update(['estado' => EstadoSolicitudEnum::PagoEnRevision, 'motivo_rechazo' => null]);
                $this->auditoria->execute(
                    accion: $version === 1 ? 'comprobante_cargado' : 'comprobante_reemplazado',
                    modelo: 'SolicitudCertificacion', modeloId: $id, actorId: $actor->id,
                    metadata: ['comprobante_id' => $evidencia->id, 'version' => $version, 'sha256' => $hash],
                );

                return $solicitud;
            });
        } catch (\Throwable $error) {
            if (is_string($ruta)) {
                Storage::disk('local')->delete($ruta);
            }
            throw $error;
        }
    }

    public function revisar(int $id, User $actor, bool $confirmar, ?string $motivo): SolicitudCertificacion
    {
        abort_unless($actor->hasRole('secretario') && ! $actor->hasRole('funcionario')
            && $actor->can($confirmar ? 'pagos.validar' : 'pagos.rechazar'), 403);
        if (! $confirmar && trim($motivo ?? '') === '') {
            throw ValidationException::withMessages(['motivo' => ['Indique el motivo del rechazo.']]);
        }
        DB::transaction(function () use ($id, $actor, $confirmar, $motivo) {
            $solicitud = SolicitudCertificacion::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($confirmar && $solicitud->requiere_pago && in_array($solicitud->estado, [EstadoSolicitudEnum::PagoConfirmado, EstadoSolicitudEnum::Generada, EstadoSolicitudEnum::ExpedicionFallida], true)) {
                return; // HTTP retry never creates another certificate or retries failed issuance silently.
            }
            if (! $solicitud->requiere_pago || $solicitud->estado !== EstadoSolicitudEnum::PagoEnRevision || $solicitud->certificado()->exists()) {
                throw new DomainException('TRANSICION_PAGO_INVALIDA');
            }
            $pago = $solicitud->pagoSoporte;
            $evidencia = $pago?->comprobantes()->reorder('version', 'desc')->first();
            if (! $evidencia || $evidencia->estado !== 'en_revision'
                || ! Storage::disk('local')->exists($evidencia->archivo_path)) {
                throw new DomainException('COMPROBANTE_REQUERIDO');
            }
            if (! hash_equals($evidencia->sha256, hash_file('sha256', Storage::disk('local')->path($evidencia->archivo_path)))) {
                throw new DomainException('COMPROBANTE_INTEGRIDAD_INVALIDA');
            }
            $evidencia->update([
                'estado' => $confirmar ? 'confirmado' : 'rechazado',
                'motivo_rechazo' => $confirmar ? null : trim($motivo),
                'revisado_por' => $actor->id, 'revisado_at' => now(),
            ]);
            $pago->update([
                'estado' => $confirmar ? EstadoPagoEnum::Confirmado : EstadoPagoEnum::Rechazado,
                'validado_por' => $actor->id, 'validado_at' => now(),
            ]);
            $solicitud->update([
                'estado' => $confirmar ? EstadoSolicitudEnum::PagoConfirmado : EstadoSolicitudEnum::PagoRechazado,
                'motivo_rechazo' => $confirmar ? null : trim($motivo),
                'reviewed_by' => $actor->id, 'reviewed_at' => now(),
            ]);
            if ($confirmar) {
                $solicitud->ordenPago?->update(['estado' => EstadoOrdenPagoEnum::Confirmado, 'confirmado_at' => now()]);
            }
            $this->auditoria->execute(
                accion: $confirmar ? 'pago_confirmado' : 'pago_rechazado', modelo: 'SolicitudCertificacion',
                modeloId: $id, actorId: $actor->id, metadata: ['comprobante_id' => $evidencia->id, 'motivo' => $confirmar ? null : trim($motivo)],
            );
        });
        if ($confirmar) {
            $this->expedirConfirmada($id, $actor);
        }

        return SolicitudCertificacion::findOrFail($id);
    }

    /** Technical retry is explicit; payment stays confirmed if institutional sources or PDF fail. */
    public function expedirConfirmada(int $id, User $actor, bool $reintento = false): void
    {
        $resultado = null;
        try {
            DB::transaction(function () use ($id, $actor, $reintento, &$resultado) {
                $solicitud = SolicitudCertificacion::whereKey($id)->lockForUpdate()->firstOrFail();
                $estados = $reintento ? [EstadoSolicitudEnum::PagoConfirmado, EstadoSolicitudEnum::ExpedicionFallida] : [EstadoSolicitudEnum::PagoConfirmado];
                if (! $solicitud->requiere_pago || ! in_array($solicitud->estado, $estados, true) || $solicitud->certificado()->exists()) {
                    return;
                }
                $resultado = $this->generar->generar($solicitud, $actor);
                $solicitud->update(['estado' => EstadoSolicitudEnum::Generada, 'error_expedicion' => null]);
                $this->auditoria->execute(
                    accion: 'generacion_posterior_pago', modelo: 'Certificado', modeloId: $resultado['certificado']->id,
                    actorId: $actor->id, metadata: ['solicitud_id' => $id, 'reintento_tecnico' => $reintento],
                );
            });
        } catch (\Throwable $error) {
            if ($resultado) {
                Storage::disk('local')->delete($resultado['certificado']->archivo_pdf_path);
            }
            $code = preg_match('/^[A-Z_]+$/', $error->getMessage()) ? $error->getMessage() : 'CERTIFICATE_GENERATION_FAILED';
            Log::error('Expedición posterior a pago fallida.', ['solicitud_id' => $id, 'code' => $code, 'exception' => $error::class]);
            DB::transaction(function () use ($id, $actor, $code) {
                $solicitud = SolicitudCertificacion::whereKey($id)->lockForUpdate()->firstOrFail();
                if ($solicitud->certificado()->exists()) {
                    return;
                }
                $solicitud->update(['estado' => EstadoSolicitudEnum::ExpedicionFallida, 'error_expedicion' => $code]);
                $this->auditoria->execute(accion: 'expedicion_pago_fallida', modelo: 'SolicitudCertificacion', modeloId: $id,
                    actorId: $actor->id, metadata: ['error' => $code, 'pago_conservado' => true]);
            });
        }
    }
}
