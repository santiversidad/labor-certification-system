<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Models\Certificado;
use Illuminate\Support\Facades\Storage;

class VerificarIntegridadCertificadoService
{
    public const OK = 'ok';

    public const ARCHIVO_AUSENTE = 'archivo_ausente';

    public const HASH_NO_COINCIDE = 'hash_no_coincide';

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function verificar(Certificado $certificado): string
    {
        if (! $certificado->archivo_pdf_path
            || ! Storage::disk('local')->exists($certificado->archivo_pdf_path)) {
            $this->registrarEvento($certificado, self::ARCHIVO_AUSENTE);

            return self::ARCHIVO_AUSENTE;
        }

        $hashPersistido = strtolower((string) $certificado->hash_pdf);
        $hashActual = hash_file(
            'sha256',
            Storage::disk('local')->path($certificado->archivo_pdf_path),
        );

        if ($hashPersistido === '' || ! hash_equals($hashPersistido, strtolower($hashActual))) {
            $this->registrarEvento($certificado, self::HASH_NO_COINCIDE);

            return self::HASH_NO_COINCIDE;
        }

        return self::OK;
    }

    private function registrarEvento(Certificado $certificado, string $resultado): void
    {
        $this->registrarAuditoria->execute(
            accion: 'integridad_certificado_comprometida',
            modelo: 'Certificado',
            modeloId: $certificado->id,
            descripcion: "No fue posible comprobar la integridad del certificado {$certificado->codigo_unico}.",
            metadata: ['resultado' => $resultado],
        );
    }
}
