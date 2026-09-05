<?php

namespace App\Services;

use App\Enums\EstadoCertificadoEnum;
use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoOrdenPagoEnum;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\Certificado;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GenerarCertificadoService
{
    public function __construct(
        private readonly PdfBasicoService $pdfBasicoService,
        private readonly TokenValidacionService $tokenValidacionService,
        private readonly ConstruirSnapshotCertificadoService $construirSnapshot,
    ) {}

    public function generar(SolicitudCertificacion $solicitud, User $generadoPor): array
    {
        $solicitud->loadMissing([
            'funcionario.cargo', 'funcionario.historialCargos.cargo', 'pagoSoporte', 'ordenPago', 'certificado',
        ]);
        $titular = $solicitud->funcionario?->user;
        if (! $titular?->estado || $solicitud->funcionario->estado !== EstadoFuncionarioEnum::Activo) {
            throw new \DomainException('INACTIVE_EMPLOYEE');
        }
        if ($titular->must_change_password) {
            throw new \DomainException('PASSWORD_CHANGE_REQUIRED');
        }

        if (! in_array($solicitud->estado, [EstadoSolicitudEnum::Aprobada, EstadoSolicitudEnum::Generando], true)) {
            throw ValidationException::withMessages([
                'solicitud' => ['La solicitud no está lista para expedición automática.'],
            ]);
        }

        $pagoConfirmado = $solicitud->ordenPago?->estado === EstadoOrdenPagoEnum::Confirmado
            || $solicitud->pagoSoporte?->estado === EstadoPagoEnum::Aprobado;
        if ($solicitud->requiere_pago && ! $pagoConfirmado) {
            throw new \DomainException('PAGO_NO_CONFIRMADO');
        }

        if ($solicitud->certificado) {
            throw ValidationException::withMessages(['certificado' => ['La solicitud ya tiene un certificado generado.']]);
        }

        $codigo = $this->codigoUnico();
        $tokenValidacionPlano = bin2hex(random_bytes(32));
        $tokenDescargaPlano = bin2hex(random_bytes(32));
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $snapshot = $this->construirSnapshot->construir($solicitud, $generadoPor);

        $html = view('certificados.laboral', [
            'snapshot' => $snapshot,
            'codigo' => $codigo,
            'urlValidacion' => "{$frontend}/validar-certificado/{$tokenValidacionPlano}",
        ])->render();
        $texto = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));
        $pdf = $this->pdfBasicoService->generarDesdeTexto($texto);
        $ruta = 'certificados/'.now()->format('Y')."/{$codigo}.pdf";

        try {
            $certificado = DB::transaction(function () use (
                $solicitud, $generadoPor, $codigo, $ruta, $pdf, $snapshot, $tokenValidacionPlano, $tokenDescargaPlano
            ) {
                $bloqueada = SolicitudCertificacion::query()->whereKey($solicitud->id)->lockForUpdate()->firstOrFail();
                if ($bloqueada->certificado()->exists()) {
                    throw ValidationException::withMessages(['certificado' => ['La solicitud ya tiene un certificado generado.']]);
                }

                Storage::disk('local')->put($ruta, $pdf);
                $certificado = Certificado::create([
                    'solicitud_certificacion_id' => $bloqueada->id,
                    'funcionario_id' => $bloqueada->funcionario_id,
                    'codigo_unico' => $codigo,
                    'archivo_pdf_path' => $ruta,
                    'hash_pdf' => hash('sha256', $pdf),
                    'snapshot_schema_version' => ConstruirSnapshotCertificadoService::SCHEMA_VERSION,
                    'snapshot_datos' => $snapshot,
                    'fecha_generacion' => now(),
                    'generado_por' => $generadoPor->id,
                    'estado' => EstadoCertificadoEnum::Vigente,
                    'download_token_hash' => hash('sha256', $tokenDescargaPlano),
                    'download_token_expires_at' => now()->addMinutes(15),
                ]);

                $this->tokenValidacionService->crearParaCertificado($certificado, $generadoPor, $tokenValidacionPlano);
                $bloqueada->update([
                    'estado' => $bloqueada->estado === EstadoSolicitudEnum::Generando
                        ? EstadoSolicitudEnum::Generada
                        : EstadoSolicitudEnum::CertificadoGenerado,
                ]);

                return $certificado;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($ruta);
            throw $exception;
        }

        return [
            'certificado' => $certificado->fresh(['funcionario', 'solicitud', 'generadoPor']),
            'token' => $tokenValidacionPlano,
            'url_validacion' => "{$frontend}/validar-certificado/{$tokenValidacionPlano}",
            'download_token' => $tokenDescargaPlano,
            'download_url' => "/api/v1/mi-certificacion/descargar/{$tokenDescargaPlano}",
        ];
    }

    private function codigoUnico(): string
    {
        do {
            $codigo = 'CL-'.now()->year.'-'.strtoupper(bin2hex(random_bytes(4)));
        } while (Certificado::where('codigo_unico', $codigo)->exists());

        return $codigo;
    }
}
