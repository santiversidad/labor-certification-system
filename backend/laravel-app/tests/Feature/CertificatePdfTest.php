<?php

namespace Tests\Feature;

use App\Domain\Manual\JsonManualParser;
use App\Services\CertificadoPdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class CertificatePdfTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        gc_collect_cycles();
        gc_mem_caches();
    }

    public function test_sencillo_genera_pdf(): void
    {
        $pdf = $this->service()->generarDesdeSnapshot($this->snapshot());

        $this->assertNotEmpty($pdf);
        $this->assertStringContainsString('CERTIFICACIÓN LABORAL', $this->text($pdf));
    }

    public function test_funciones_genera_pdf(): void
    {
        $pdf = $this->service()->generarDesdeSnapshot($this->snapshot('funciones'));

        $this->assertStringContainsString('CON FUNCIONES', $this->text($pdf));
    }

    public function test_salida_tiene_cabecera_pdf_valida(): void
    {
        $this->assertStringStartsWith('%PDF-', $this->service()->generarDesdeSnapshot($this->snapshot()));
    }

    public function test_url_and_html_in_employee_data_cannot_become_remote_pdf_resources(): void
    {
        $snapshot = $this->snapshot();
        $payload = '<img src="http://127.0.0.1:9/synthetic-security-resource" onerror="alert(1)">';
        $snapshot['funcionario']['nombres'] = $payload;
        $html = $this->service()->renderHtml($snapshot);
        $this->assertStringContainsString(e($payload), $html);
        $this->assertStringNotContainsString($payload, $html);
        $options = (new \ReflectionMethod(CertificadoPdfService::class, 'options'))->invoke($this->service());
        $this->assertFalse($options->getIsRemoteEnabled());
        $this->assertFalse($options->getIsPhpEnabled());
        [$allowed] = $options->validateRemoteUri('http://127.0.0.1:9/synthetic-security-resource');
        $this->assertFalse($allowed); // Validation only: no network request is issued.
        $this->assertStringStartsWith('%PDF-', $this->service()->generarDesdeSnapshot($snapshot));
    }

    public function test_sencillo_no_imprime_funciones_aunque_se_inyecte_manual(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['manual_funciones'] = $this->manual();
        $text = $this->text($this->service()->generarDesdeSnapshot($snapshot));

        $this->assertStringNotContainsString('Funciones esenciales', $text);
        $this->assertStringNotContainsString('Función fixture con énfasis técnico.', $text);
    }

    public function test_funciones_imprime_funcion_fixture_sin_reescribirla(): void
    {
        $text = $this->text($this->service()->generarDesdeSnapshot($this->snapshot('funciones')));

        $this->assertStringContainsString('Función fixture con énfasis técnico.', $text);
    }

    public function test_pdf_no_imprime_salario_ni_requisitos(): void
    {
        $snapshot = $this->snapshot('funciones');
        $snapshot['salario'] = ['valor' => 9999999];
        $snapshot['manual_funciones']['conocimientos'] = ['Contenido que no se debe imprimir'];
        $snapshot['manual_funciones']['requisitos'] = ['Experiencia que no se debe imprimir'];
        $text = $this->text($this->service()->generarDesdeSnapshot($snapshot));

        $this->assertDoesNotMatchRegularExpression('/salario|9[.]999[.]999|conocimientos|experiencia que no/iu', $text);
    }

    public function test_pdf_conserva_unicode_y_acentos(): void
    {
        $service = $this->service();
        $snapshot = $this->snapshot('funciones');
        $html = $service->renderHtml($snapshot);
        $text = $this->text($service->generarDesdeSnapshot($snapshot));

        $this->assertStringContainsString('María José Muñoz Álvarez', $html);
        // Smalot can omit the first fragment of a justified Dompdf line. The
        // HTML verifies the full source; the PDF checks remaining Unicode text.
        $this->assertStringContainsString('Álvarez', $text);
        $this->assertStringContainsString('Planeación e Innovación Pública', $text);
        $this->assertStringContainsString('Función fixture con énfasis técnico.', $text);
    }

    public function test_muchas_funciones_producen_pdf_multipagina(): void
    {
        $pdf = $this->service()->generarDesdeSnapshot($this->snapshot('funciones', 14));

        $this->assertGreaterThan(1, count((new Parser)->parseContent($pdf)->getPages()));
    }

    public function test_pdf_multipagina_incluye_pagina_actual_y_total(): void
    {
        $parsed = (new Parser)->parseContent(
            $this->service()->generarDesdeSnapshot($this->snapshot('funciones', 14))
        );
        $pages = $parsed->getPages();

        $this->assertMatchesRegularExpression('/Página\s+1\s+de\s+'.count($pages).'/u', $pages[0]->getText());
        $this->assertMatchesRegularExpression('/Página\s+'.count($pages).'\s+de\s+'.count($pages).'/u', end($pages)->getText());
    }

    public function test_marca_borrador_aparece_en_todas_las_paginas(): void
    {
        $pages = (new Parser)->parseContent(
            $this->service()->generarDesdeSnapshot($this->snapshot('funciones', 14))
        )->getPages();

        foreach ($pages as $page) {
            $this->assertStringContainsString('BORRADOR', $page->getText());
            $this->assertStringContainsString('SIN VALIDEZ OFICIAL', $page->getText());
        }
    }

    public function test_pdf_no_contiene_firma_ni_identidad_de_firmante(): void
    {
        $text = $this->text($this->service()->generarDesdeSnapshot($this->snapshot('funciones')));

        $this->assertDoesNotMatchRegularExpression('/firma|firmante|responsable:/iu', $text);
        $this->assertStringContainsString('Mecanismo de oficialización pendiente de definición institucional', $text);
    }

    public function test_pdf_no_contiene_qr_oficial(): void
    {
        $text = $this->text($this->service()->generarDesdeSnapshot($this->snapshot('funciones')));

        $this->assertDoesNotMatchRegularExpression('/\bQR\b|código QR/iu', $text);
    }

    public function test_render_requiere_snapshot_schema_v3(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['schema_version'] = 2;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('CERTIFICATE_SNAPSHOT_VERSION_UNSUPPORTED');
        $this->service()->generarDesdeSnapshot($snapshot);
    }

    public function test_render_desde_snapshot_no_consulta_relaciones_vivas(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $snapshot = $this->snapshot('funciones');
        $snapshot['cargo']['denominacion'] = 'Dato congelado del snapshot';
        $text = $this->text($this->service()->generarDesdeSnapshot($snapshot));

        $this->assertSame(0, $queries);
        $this->assertStringContainsString('Dato congelado del snapshot', $text);
    }

    public function test_funciones_conservan_el_orden_del_snapshot(): void
    {
        $text = $this->service()->renderHtml($this->snapshot('funciones', 3));

        $this->assertLessThan(strpos($text, 'Función sintética 2'), strpos($text, 'Función fixture con énfasis'));
        $this->assertLessThan(strpos($text, 'Función sintética 3'), strpos($text, 'Función sintética 2'));
    }

    public function test_campos_vacios_no_se_imprimen_como_null_ni_na(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['funcionario']['nombres'] = null;
        $snapshot['funcionario']['apellidos'] = 'undefined';
        $snapshot['cargo']['dependencia'] = 'N/A';
        $snapshot['expedicion']['radicado'] = null;
        $html = $this->service()->renderHtml($snapshot);

        $this->assertStringNotContainsString('undefined', $html);
        $this->assertStringNotContainsString('>N/A<', $html);
        $this->assertStringNotContainsString('>null<', $html);
        $this->assertStringNotContainsString('bajo el radicado', $html);
    }

    public function test_evidencia_mf0002_contiene_once_funciones_reales_en_orden(): void
    {
        $path = storage_path('framework/testing/pdf-evidence-'.uniqid());
        try {
            $this->artisan('certificates:generate-pdf-evidence', ['--output' => $path])->assertSuccessful();
            $pdf = File::get($path.'/certificado-funciones-mf0002-borrador.pdf');
            $text = $this->text($pdf);
            $source = file_get_contents(database_path('data/manual_funciones_decreto_015_2023.json'));
            $profile = collect(app(JsonManualParser::class)->parse($source))
                ->first(fn ($item) => $item->origen['perfil_id'] === 'MF-0002');
            $this->assertCount(11, $profile->origen['funciones']);
            $this->assertStringContainsString('MF-0002', $text);
            $this->assertStringContainsString('Secretario de Despacho 020-02', $text);
            $this->assertCount(3, (new Parser)->parseContent($pdf)->getPages());

            $snapshot = [
                'schema_version' => 3,
                'tipo_certificado' => 'funciones',
                'manual_funciones' => [
                    'funciones' => array_map(static fn ($function) => [
                        'orden' => $function['orden'], 'descripcion' => $function['texto'],
                    ], $profile->origen['funciones']),
                ],
            ];
            $html = $this->service()->renderHtml($snapshot);

            $lastPosition = -1;
            foreach ($profile->origen['funciones'] as $function) {
                $position = strpos($html, e($function['texto']));
                $this->assertNotFalse($position, 'La función '.$function['orden'].' falta o fue reescrita.');
                $this->assertGreaterThan($lastPosition, $position);
                $lastPosition = $position;
                $this->assertMatchesRegularExpression('/(?:^|\s)'.$function['orden'].'\./u', $text);
            }
        } finally {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            }
        }
    }

    private function service(): CertificadoPdfService
    {
        return app(CertificadoPdfService::class);
    }

    private function text(string $pdf): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (new Parser)->parseContent($pdf)->getText()));
    }

    private function snapshot(string $type = 'sencillo', int $functionCount = 1): array
    {
        $snapshot = [
            'schema_version' => 3,
            'tipo_certificado' => $type,
            'fecha_generacion' => '2026-09-23T10:30:00-05:00',
            'funcionario' => [
                'nombres' => 'María José',
                'apellidos' => 'Muñoz Álvarez',
                'tipo_documento' => 'CC',
                'numero_documento' => '1000000001',
                'fecha_ingreso' => '2020-01-15',
                'fecha_retiro' => null,
            ],
            'cargo' => [
                'denominacion' => 'Profesional Universitario',
                'codigo' => '219',
                'grado' => '02',
                'dependencia' => 'Planeación e Innovación Pública',
            ],
            'asignacion' => [
                'tipo_vinculacion' => 'planta',
                'fecha_inicio' => '2020-01-15',
                'fecha_fin' => null,
            ],
            'expedicion' => [
                'fecha_expedicion' => '2026-09-23',
                'radicado' => 'CL-2026-000001',
                'codigo_tecnico' => 'CL-2026-ABC12345',
                'url_validacion_tecnica' => 'http://localhost:3200/validar-certificado/token-ficticio',
            ],
        ];

        if ($type === 'funciones') {
            $snapshot['manual_funciones'] = $this->manual($functionCount);
        }

        return $snapshot;
    }

    private function manual(int $functionCount = 1): array
    {
        $functions = [[
            'orden' => 99,
            'descripcion' => 'Función fixture con énfasis técnico.',
        ]];

        for ($index = 2; $index <= $functionCount; $index++) {
            $functions[] = [
                'orden' => 100 - $index,
                'descripcion' => "Función sintética {$index}: gestionar información institucional con precisión, trazabilidad y criterios de calidad, conservando íntegramente el texto capturado en el snapshot de emisión.",
            ];
        }

        return [
            'manual_nombre' => 'Manual Específico de Funciones',
            'version' => '2026.1',
            'source_id' => 'FICHA-TEST-001',
            'area_funcional' => 'Gestión estratégica',
            'proposito_principal' => 'Orientar la planeación institucional con criterios técnicos y públicos.',
            'funciones' => $functions,
            'prueba_desarrollo' => false,
        ];
    }
}
