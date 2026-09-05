<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoCertificadoEnum;
use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Services\VerificarIntegridadCertificadoService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MiCertificacionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VerificarIntegridadCertificadoService $verificarIntegridad,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function descargar(Request $request, string $token)
    {
        $funcionario = $request->user()->funcionario;
        $certificado = Certificado::query()
            ->where('download_token_hash', hash('sha256', $token))
            ->where('funcionario_id', $funcionario?->id)
            ->where('download_token_expires_at', '>', now())
            ->firstOrFail();

        if ($certificado->estado === EstadoCertificadoEnum::Anulado) {
            return $this->errorResponse('El certificado está anulado y no puede descargarse.', null, 409, 'CERTIFICATE_ANNULLED');
        }

        $integridad = $this->verificarIntegridad->verificar($certificado);
        if ($integridad === VerificarIntegridadCertificadoService::ARCHIVO_AUSENTE) {
            return $this->notFoundResponse('No se encontró el archivo PDF del certificado.');
        }
        if ($integridad !== VerificarIntegridadCertificadoService::OK) {
            return $this->errorResponse('El documento no pudo validarse y no será entregado.', null, 409, 'CERTIFICATE_INTEGRITY_FAILURE');
        }

        if ($certificado->estado === EstadoCertificadoEnum::Vigente) {
            $certificado->update(['estado' => EstadoCertificadoEnum::Descargado]);
        }
        $this->registrarAuditoria->execute(
            accion: 'descargar_certificado_autoservicio',
            modelo: 'Certificado',
            modeloId: $certificado->id,
            descripcion: 'El funcionario descargó su certificación recién expedida.',
            metadata: ['actor_funcionario_id' => $funcionario->id],
        );

        return response()->download(
            Storage::disk('local')->path($certificado->archivo_pdf_path),
            "{$certificado->codigo_unico}.pdf",
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
