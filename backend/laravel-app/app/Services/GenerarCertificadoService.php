<?php

namespace App\Services;

use App\Enums\EstadoCertificadoEnum;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\Certificado;
use App\Models\RangoSalarial;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GenerarCertificadoService
{
    public function __construct(
        private readonly PdfBasicoService $pdfBasicoService,
        private readonly TokenValidacionService $tokenValidacionService,
    ) {}

    public function generar(SolicitudCertificacion $solicitud, User $generadoPor): array
    {
        $solicitud->loadMissing('funcionario.cargo', 'pagoSoporte', 'certificado');

        if ($solicitud->estado !== EstadoSolicitudEnum::Aprobada) {
            throw ValidationException::withMessages([
                'solicitud' => ['Solo se puede generar certificado de una solicitud aprobada.'],
            ]);
        }

        if ($solicitud->requiere_pago && $solicitud->pagoSoporte?->estado !== EstadoPagoEnum::Aprobado) {
            throw ValidationException::withMessages([
                'pago' => ['No se puede generar certificado si el pago requerido no esta aprobado.'],
            ]);
        }

        if ($solicitud->certificado) {
            throw ValidationException::withMessages([
                'certificado' => ['La solicitud ya tiene un certificado generado.'],
            ]);
        }

        $codigo = $this->codigoUnico();
        $token = bin2hex(random_bytes(8));
        $urlValidacion = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
            . "/validar-certificado/{$token}";

        $html = view('certificados.laboral', [
            'solicitud'     => $solicitud,
            'funcionario'   => $solicitud->funcionario,
            'cargo'         => $solicitud->funcionario?->cargo,
            'salario'       => $this->salarioPara($solicitud),
            'codigo'        => $codigo,
            'fecha'         => now(),
            'urlValidacion' => $urlValidacion,
        ])->render();

        $texto = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));
        $pdf = $this->pdfBasicoService->generarDesdeTexto($texto);
        $ruta = 'certificados/' . now()->format('Y') . "/{$codigo}.pdf";

        Storage::disk('local')->put($ruta, $pdf);

        $certificado = Certificado::create([
            'solicitud_certificacion_id' => $solicitud->id,
            'funcionario_id'             => $solicitud->funcionario_id,
            'codigo_unico'               => $codigo,
            'archivo_pdf_path'           => $ruta,
            'hash_pdf'                   => hash('sha256', $pdf),
            'fecha_generacion'           => now(),
            'generado_por'               => $generadoPor->id,
            'estado'                     => EstadoCertificadoEnum::Vigente,
        ]);

        $tokenValidacion = $this->tokenValidacionService->crearParaCertificado($certificado, $generadoPor, $token);

        $solicitud->update([
            'estado'      => EstadoSolicitudEnum::CertificadoGenerado,
            'reviewed_by' => $generadoPor->id,
            'reviewed_at' => now(),
        ]);

        return [
            'certificado'    => $certificado->fresh(['funcionario', 'solicitud', 'generadoPor']),
            'token'          => $tokenValidacion,
            'url_validacion' => rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/')
                . "/validar-certificado/{$tokenValidacion}",
        ];
    }

    private function codigoUnico(): string
    {
        do {
            $codigo = 'CL-' . now()->year . '-' . strtoupper(bin2hex(random_bytes(4)));
        } while (Certificado::where('codigo_unico', $codigo)->exists());

        return $codigo;
    }

    private function salarioPara(SolicitudCertificacion $solicitud): ?string
    {
        if (! $solicitud->requiere_salario || ! $solicitud->funcionario?->cargo) {
            return null;
        }

        $cargo = $solicitud->funcionario->cargo;
        $rango = RangoSalarial::where('codigo', $cargo->codigo)
            ->where('grado', $cargo->grado)
            ->where('vigencia_anio', now()->year)
            ->where('estado', true)
            ->first();

        return $rango?->salario_basico;
    }
}
