<?php

namespace App\Console\Commands;

use App\Services\CertificadoPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateCertificatePdfEvidence extends Command
{
    protected $signature = 'certificates:generate-pdf-evidence {--output=}';

    protected $description = 'Generate three synthetic Phase 3A draft certificate PDFs outside product storage';

    public function handle(CertificadoPdfService $pdfService): int
    {
        $output = $this->option('output') ?: config('certificate_pdf.evidence_path');
        File::ensureDirectoryExists($output);

        $documents = [
            'certificado-sencillo-borrador.pdf' => $this->snapshot('sencillo'),
            'certificado-funciones-borrador.pdf' => $this->snapshot('funciones', 3),
            'certificado-funciones-multipagina-borrador.pdf' => $this->snapshot('funciones', 32),
        ];

        foreach ($documents as $filename => $snapshot) {
            File::put($output.DIRECTORY_SEPARATOR.$filename, $pdfService->generarDesdeSnapshot($snapshot));
            $this->line($output.DIRECTORY_SEPARATOR.$filename);
        }

        return self::SUCCESS;
    }

    private function snapshot(string $type, int $functionCount = 0): array
    {
        $snapshot = [
            'schema_version' => 3,
            'tipo_certificado' => $type,
            'fecha_generacion' => '2026-09-23T10:30:00-05:00',
            'funcionario' => [
                'nombres' => 'María Ejemplo',
                'apellidos' => 'Pérez Ficticia',
                'tipo_documento' => 'CC',
                'numero_documento' => '1000000001',
                'fecha_ingreso' => '2021-02-08',
                'fecha_retiro' => null,
            ],
            'cargo' => [
                'denominacion' => 'Profesional Universitario',
                'codigo' => '219',
                'grado' => '02',
                'nivel' => 'Profesional',
                'dependencia' => 'Dependencia Ficticia de Desarrollo Institucional',
            ],
            'asignacion' => [
                'tipo_vinculacion' => 'planta',
                'naturaleza_cargo' => 'carrera_administrativa',
                'fecha_inicio' => '2021-02-08',
                'fecha_fin' => null,
            ],
            'expedicion' => [
                'fecha_expedicion' => '2026-09-23',
                'radicado' => 'DEV-2026-FICTICIO',
                'codigo_tecnico' => 'BORRADOR-DEV-0001',
                'url_validacion_tecnica' => 'http://localhost:3200/validar-certificado/token-ficticio-no-oficial',
            ],
        ];

        if ($type === 'funciones') {
            $functions = [];
            for ($order = 1; $order <= $functionCount; $order++) {
                $functions[] = [
                    'orden' => $order,
                    'descripcion' => "Función ficticia {$order}: apoyar procesos administrativos de ejemplo con precisión, trazabilidad y oportunidad, preservando el texto íntegro registrado en el snapshot de desarrollo.",
                ];
            }
            $snapshot['manual_funciones'] = [
                'manual_nombre' => 'Manual Ficticio para Evidencia de Desarrollo',
                'version' => 'DEV-2026',
                'source_id' => 'FICHA-FICTICIA-001',
                'area_funcional' => 'Área Funcional de Ejemplo',
                'proposito_principal' => 'Apoyar de forma ficticia la gestión institucional para verificar el diseño documental.',
                'funciones' => $functions,
                'prueba_desarrollo' => false,
            ];
        }

        return $snapshot;
    }
}
