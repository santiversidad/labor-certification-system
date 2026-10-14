<?php

namespace App\Console\Commands;

use App\Domain\Manual\JsonManualParser;
use App\Services\CertificadoPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateCertificatePdfEvidence extends Command
{
    protected $signature = 'certificates:generate-pdf-evidence {--output=}';

    protected $description = 'Generate three fictitious Phase 3A drafts, including real MF-0002 functions';

    public function handle(CertificadoPdfService $pdfService, JsonManualParser $parser): int
    {
        $output = $this->option('output') ?: config('certificate_pdf.evidence_path');
        File::ensureDirectoryExists($output);

        $mf0002 = $this->mf0002($parser);

        $documents = [
            'certificado-sencillo-borrador.pdf' => $this->snapshot('sencillo'),
            'certificado-funciones-mf0002-borrador.pdf' => $this->snapshot('funciones', $mf0002['manual'], $mf0002['cargo']),
            'certificado-funciones-multipagina-borrador.pdf' => $this->snapshot('funciones', $this->syntheticManual(32)),
        ];

        foreach ($documents as $filename => $snapshot) {
            $start = microtime(true);
            File::put($output.DIRECTORY_SEPARATOR.$filename, $pdfService->generarDesdeSnapshot($snapshot));
            $this->line(sprintf('%s %.0f ms', $output.DIRECTORY_SEPARATOR.$filename, (microtime(true) - $start) * 1000));
        }

        return self::SUCCESS;
    }

    private function snapshot(string $type, ?array $manual = null, ?array $cargo = null): array
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
            'cargo' => $cargo ?? [
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
                'codigo_tecnico' => null,
                'url_validacion_tecnica' => null,
            ],
        ];

        if ($type === 'funciones') {
            $snapshot['manual_funciones'] = $manual;
        }

        return $snapshot;
    }

    private function mf0002(JsonManualParser $parser): array
    {
        $source = database_path('data/manual_funciones_decreto_015_2023.json');
        if (hash_file('sha256', $source) !== '261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e') {
            throw new \DomainException('MANUAL_EVIDENCE_SOURCE_UNVALIDATED');
        }

        foreach ($parser->parse(file_get_contents($source)) as $profile) {
            if ($profile->origen['perfil_id'] !== 'MF-0002') {
                continue;
            }

            $origin = $profile->origen;
            if (count($origin['funciones']) !== 11 || ! trim($origin['area_funcional']) || ! trim($origin['proposito_principal'])) {
                throw new \DomainException('MANUAL_EVIDENCE_MF0002_INCOMPLETE');
            }

            return ['cargo' => [
                'denominacion' => $origin['denominacion'],
                'codigo' => $origin['codigo'],
                'grado' => $origin['grado'],
                'nivel' => $origin['nivel'],
                'dependencia' => $origin['dependencia'],
            ], 'manual' => [
                'manual_nombre' => 'Manual de Funciones y Competencias Laborales - Villavicencio',
                'version' => 'Decreto 1000-24/015 de 2023',
                'source_id' => 'MF-0002',
                'area_funcional' => $origin['area_funcional'],
                'proposito_principal' => $origin['proposito_principal'],
                'funciones' => array_map(static fn (array $function): array => [
                    'orden' => $function['orden'], 'descripcion' => $function['texto'],
                ], $origin['funciones']),
                'prueba_desarrollo' => false,
            ]];
        }

        throw new \DomainException('MANUAL_EVIDENCE_MF0002_NOT_FOUND');
    }

    private function syntheticManual(int $functionCount): array
    {
        $functions = [];
        for ($order = 1; $order <= $functionCount; $order++) {
            $functions[] = [
                'orden' => $order,
                'descripcion' => "Función ficticia {$order}: apoyar procesos administrativos de ejemplo con precisión, trazabilidad y oportunidad, preservando el texto íntegro registrado en el snapshot de desarrollo.",
            ];
        }

        return [
            'manual_nombre' => 'Manual ficticio para prueba multipágina',
            'version' => 'Prueba de desarrollo',
            'source_id' => 'FICHA-FICTICIA-001',
            'area_funcional' => 'Área funcional de ejemplo',
            'proposito_principal' => 'Verificar la composición documental con texto sintético extenso.',
            'funciones' => $functions,
            'prueba_desarrollo' => false,
        ];
    }
}
