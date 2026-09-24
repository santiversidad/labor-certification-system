<?php

namespace Tests\Feature;

use App\Services\CertificadoPdfService;
use Illuminate\Support\Facades\DB;
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
        $this->assertStringContainsString('María', $text);
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
