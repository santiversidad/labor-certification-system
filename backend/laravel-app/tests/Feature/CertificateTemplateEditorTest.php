<?php

namespace Tests\Feature;

use App\Exceptions\CertificateTemplateException;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\PlantillaAsset;
use App\Models\PlantillaCertificado;
use App\Models\PlantillaVersion;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\CertificateTemplateDefinition;
use App\Services\CertificateTemplatePreview;
use App\Services\CertificateTemplateRenderer;
use App\Services\CertificateTemplateService;
use App\Services\GenerarCertificadoService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Smalot\PdfParser\Parser;
use Tests\Support\ManualFixture;
use Tests\TestCase;

class CertificateTemplateEditorTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private string $root = '/api/v1/admin/plantillas-certificados';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->backoffice()->create(['estado' => true]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin, 'sanctum');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        gc_collect_cycles();
        gc_mem_caches();
    }

    private function draft(string $type = 'funciones'): PlantillaVersion
    {
        return app(CertificateTemplateService::class)->createDraft(PlantillaCertificado::where('tipo', $type)->sole()->id, $this->admin);
    }

    private function snapshot(PlantillaVersion $version, string $scenario = 'breve'): array
    {
        $type = $version->plantilla->tipo;
        $snapshot = app(CertificateTemplatePreview::class)->snapshot($type, $scenario);
        $snapshot['plantilla_certificado'] = app(CertificateTemplateService::class)->envelope($version, $type, $version->contenido);

        return $snapshot;
    }

    public function test_admin_consulta_dos_tipos_crea_borrador_y_catalogo_controlado(): void
    {
        $this->getJson($this->root)->assertOk()->assertJsonCount(2, 'data');
        $template = PlantillaCertificado::where('tipo', 'funciones')->sole();
        $response = $this->postJson($this->root.'/'.$template->id.'/versiones')->assertCreated();
        $response->assertJsonPath('data.estado', 'borrador')->assertJsonPath('data.revision', 1)->assertJsonPath('data.contenido.schema_version', 1)->assertJsonCount(2, 'data.assets');
        $this->getJson($this->root.'/catalogo?tipo=sencillo')->assertOk()->assertJsonMissing(['id' => 'pagina_manual'])->assertJsonMissing(['id' => 'salario']);
        $this->assertSame(37, $this->admin->getAllPermissions()->count());
    }

    public static function otherRoles(): array
    {
        return [['secretario'], ['funcionario']];
    }

    #[DataProvider('otherRoles')]
    public function test_otros_roles_no_acceden_a_nigun_endpoint(string $role): void
    {
        $version = $this->draft();
        $user = $role === 'secretario' ? User::factory()->backoffice()->create(['estado' => true]) : User::factory()->create(['estado' => true]);
        $user->assignRole($role);
        $user->givePermissionTo('parametros.editar'); // The role check is also mandatory.
        $this->actingAs($user, 'sanctum');
        foreach ([$this->root, $this->root.'/catalogo?tipo=funciones', $this->root.'/versiones/'.$version->id,
            $this->root.'/'.$version->plantilla_id.'/versiones', $this->root.'/assets/'.$version->contenido['header']['left_asset_id']] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $version->contenido])->assertForbidden();
        $this->postJson($this->root.'/versiones/'.$version->id.'/preview', ['escenario' => 'breve'])->assertForbidden();
        $this->postJson($this->root.'/versiones/'.$version->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertForbidden();
    }

    public function test_edicion_estructurada_se_guarda_con_revision_y_auditoria_sin_textos(): void
    {
        $version = $this->draft();
        $content = $version->contenido;
        $content['title'] = 'TÍTULO SINTÉTICO';
        $content['paragraphs'][] = ['id' => 'opcional', 'position' => 'body', 'alignment' => 'left', 'runs' => [
            ['type' => 'text', 'text' => 'Párrafo confidencial sintético.'], ['type' => 'variable', 'variable' => 'nombre_completo', 'bold' => true],
        ]];
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $content])->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertSame(CertificateTemplateDefinition::hash($content), $version->fresh()->sha256);
        $audit = DB::table('audit_logs')->where('accion', 'plantilla_actualizada')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('Párrafo confidencial', json_encode($audit));
        $this->assertStringContainsString('paragraphs', json_encode($audit));
    }

    public static function invalidContent(): array
    {
        return [['unknown'], ['variable'], ['html'], ['css'], ['php'], ['missing'], ['duplicate'], ['large'], ['salary'], ['path'], ['signature_image'], ['functions_edit'], ['font']];
    }

    #[DataProvider('invalidContent')]
    public function test_payload_peligroso_o_invalido_no_se_persiste(string $case): void
    {
        $version = $this->draft();
        $content = $version->contenido;
        switch ($case) {
            case 'unknown': $content['blocks'] = [['type' => 'script']];
                break;
            case 'variable': $content['paragraphs'][0]['runs'] = [['type' => 'variable', 'variable' => 'usuario.password']];
                break;
            case 'html': $content['title'] = '<img src="https://attacker.invalid/x" onerror="alert(1)">';
                break;
            case 'css': $content['typography']['css'] = 'position:absolute';
                break;
            case 'php': $content['paragraphs'][0]['runs'] = [['type' => 'text', 'text' => '{{ system("whoami") }}']];
                break;
            case 'missing': array_shift($content['paragraphs']);
                break;
            case 'duplicate': $content['paragraphs'][] = $content['paragraphs'][0];
                break;
            case 'large': $content['title'] = str_repeat('A', 141);
                break;
            case 'salary': $content['paragraphs'][0]['runs'] = [['type' => 'variable', 'variable' => 'salario_override']];
                break;
            case 'path': $content['header']['ruta'] = '/etc/passwd';
                break;
            case 'signature_image': $content['signature']['image'] = 'firma.png';
                break;
            case 'functions_edit': $content['functions']['funciones'] = ['Función inventada'];
                break;
            case 'font': $content['typography']['font'] = 'Fuente externa';
                break;
        }
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $content])->assertUnprocessable();
        $this->assertSame(1, $version->fresh()->revision);
    }

    public function test_sencillo_rechaza_bloque_funciones_y_variable_manual(): void
    {
        $version = $this->draft('sencillo');
        foreach ([[], ['title' => 'Funciones', 'size' => 10, 'line_height' => 1.4]] as $functions) {
            $content = $version->contenido;
            $content['functions'] = $functions;
            $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $content])->assertUnprocessable();
        }
        $content = $version->contenido;
        $content['paragraphs'][0]['runs'] = [['type' => 'variable', 'variable' => 'pagina_manual']];
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $content])->assertUnprocessable();
    }

    public function test_publicacion_confirmada_nueva_version_y_concurrencia_logica(): void
    {
        $version = $this->draft();
        $this->postJson($this->root.'/versiones/'.$version->id.'/publicar', ['revision' => 1])->assertUnprocessable();
        $this->postJson($this->root.'/versiones/'.$version->id.'/publicar', ['revision' => 2, 'confirmado' => true])->assertConflict()->assertJsonPath('code', 'TEMPLATE_REVISION_CONFLICT');
        $this->postJson($this->root.'/versiones/'.$version->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertOk()->assertJsonPath('data.estado', 'publicada');
        $first = app(CertificateTemplateService::class)->forSnapshot('funciones');
        $next = $this->draft();
        $this->assertSame(2, $next->numero);
        $this->assertSame(CertificateTemplateDefinition::hash($version->contenido), CertificateTemplateDefinition::hash($next->contenido));
        $this->postJson($this->root.'/versiones/'.$next->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertOk();
        $this->assertSame('archivada', $version->fresh()->estado);
        $this->assertSame($next->id, PlantillaCertificado::find($next->plantilla_id)->publicada_id);
        $this->assertSame($version->id, $first['id']);
        $this->postJson($this->root.'/versiones/'.$next->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertConflict();
        $this->assertSame(1, PlantillaVersion::where('estado', 'publicada')->count());
    }

    public function test_borrador_publicado_es_inmutable_en_api_y_sql(): void
    {
        $version = $this->draft();
        app(CertificateTemplateService::class)->publish($version->id, 1, $this->admin);
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $version->contenido])->assertConflict();
        $this->expectException(QueryException::class);
        $version->update(['contenido' => ['tampered' => true]]);
    }

    public function test_revision_anterior_no_sobrescribe_y_no_duplica_borrador(): void
    {
        $version = $this->draft();
        $this->postJson($this->root.'/'.$version->plantilla_id.'/versiones')->assertConflict();
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $version->contenido])->assertOk();
        $this->putJson($this->root.'/versiones/'.$version->id, ['revision' => 1, 'contenido' => $version->contenido])->assertConflict();
        $this->assertSame(2, $version->fresh()->revision);
    }

    public function test_publicacion_con_recursos_ausentes_se_rechaza(): void
    {
        $version = $this->draft();
        $asset = PlantillaAsset::find($version->contenido['header']['left_asset_id']);
        Storage::disk('local')->delete($asset->getRawOriginal('ruta'));
        $this->postJson($this->root.'/versiones/'.$version->id.'/publicar', ['revision' => 1, 'confirmado' => true])->assertConflict()->assertJsonPath('code', 'TEMPLATE_ASSET_MISSING');
        $this->assertSame('borrador', $version->fresh()->estado);
    }

    public function test_imagen_valida_se_normaliza_reemplaza_y_conserva_original(): void
    {
        $version = $this->draft();
        $oldId = $version->contenido['header']['left_asset_id'];
        $response = $this->post($this->root.'/versiones/'.$version->id.'/assets', ['posicion' => 'left', 'procedencia' => 'Fixture sintético', 'archivo' => UploadedFile::fake()->image('logo.jpg', 1000, 800)], ['Accept' => 'application/json'])->assertCreated();
        $response->assertJsonPath('data.mime', 'image/png')->assertJsonPath('data.ancho', 400)->assertJsonPath('data.alto', 320);
        $this->assertNotSame($oldId, $response->json('data.id'));
        $this->get($this->root.'/assets/'.$oldId)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($oldId, $version->fresh()->contenido['header']['left_asset_id']); // Upload does not silently save a draft.
    }

    public function test_imagen_corrupta_dimensiones_proporcion_y_svg_se_rechazan(): void
    {
        $version = $this->draft();
        foreach ([UploadedFile::fake()->createWithContent('invalid.png', 'not an image'), UploadedFile::fake()->image('tiny.png', 10, 10),
            UploadedFile::fake()->image('wide.png', 300, 50), UploadedFile::fake()->createWithContent('script.svg', '<svg onload="alert(1)"/>')] as $file) {
            $this->post($this->root.'/versiones/'.$version->id.'/assets', ['posicion' => 'left', 'procedencia' => 'Prueba', 'archivo' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        }
    }

    public function test_webp_y_orientacion_jpeg_se_normalizan_sin_metadatos_exif(): void
    {
        $version = $this->draft();
        $image = imagecreatetruecolor(600, 200);
        ob_start();
        imagewebp($image);
        $webp = ob_get_clean();
        imagedestroy($image);
        $this->post($this->root.'/versiones/'.$version->id.'/assets', ['posicion' => 'right', 'procedencia' => 'WebP sintético',
            'archivo' => UploadedFile::fake()->createWithContent('banner.webp', $webp)], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.mime', 'image/png')->assertJsonPath('data.ancho', 600);
        $rotated = file_get_contents(base_path('tests/Fixtures/certificate-template/orientation-6.jpg'));
        $this->post($this->root.'/versiones/'.$version->id.'/assets', ['posicion' => 'left', 'procedencia' => 'JPEG sintético con orientación 6',
            'archivo' => UploadedFile::fake()->createWithContent('rotated.jpg', $rotated)], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.ancho', 80)->assertJsonPath('data.alto', 120);
    }

    public function test_asset_versionado_no_permite_mutacion_sql(): void
    {
        $version = $this->draft();
        $asset = PlantillaAsset::find($version->contenido['header']['left_asset_id']);
        $this->expectException(QueryException::class);
        $asset->update(['sha256' => str_repeat('0', 64)]);
    }

    public function test_emision_congela_version_correcta_y_no_reescribe_pdf_legacy_o_publicado(): void
    {
        $user = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $user->assignRole('funcionario');
        $cargo = Cargo::create(['codigo' => 'TPL', 'grado' => '01', 'denominacion' => 'Cargo sintético', 'nivel' => 'Profesional', 'estado' => true]);
        $person = Funcionario::create(['user_id' => $user->id, 'tipo_documento' => 'CC', 'numero_documento' => $user->documento,
            'nombres' => 'Persona', 'apellidos' => 'Sintética', 'estado' => 'activo', 'fecha_ingreso' => '2020-01-15', 'dependencia' => 'Unidad sintética', 'cargo_id' => $cargo->id]);
        ManualFixture::vincular($person);
        $request = fn (string $type) => SolicitudCertificacion::create(['funcionario_id' => $person->id, 'tipo_certificado' => $type, 'estado' => 'aprobada', 'created_by' => $user->id]);
        $generator = app(GenerarCertificadoService::class);
        $legacy = $generator->generar($request('sencillo'), $user)['certificado'];
        $legacyBytes = Storage::disk('local')->get($legacy->archivo_pdf_path);
        $legacySnapshot = $legacy->snapshot_datos;
        $version = $this->draft();
        app(CertificateTemplateService::class)->publish($version->id, 1, $this->admin);
        $issued = $generator->generar($request('funciones'), $user)['certificado'];
        $snapshot = $issued->snapshot_datos;
        $bytes = Storage::disk('local')->get($issued->archivo_pdf_path);
        $this->assertSame($version->id, $snapshot['plantilla_certificado']['id']);
        $this->assertSame($version->sha256, $snapshot['plantilla_certificado']['sha256']);
        $this->assertSame('Función sintética de pruebas.', $snapshot['manual_funciones']['funciones'][0]['descripcion']);
        $this->assertStringContainsString('Función sintética de pruebas.', (new Parser)->parseContent($bytes)->getText());
        $next = $this->draft();
        $content = $next->contenido;
        $content['title'] = 'FORMATO POSTERIOR';
        app(CertificateTemplateService::class)->save($next->id, 1, $content, $this->admin);
        app(CertificateTemplateService::class)->publish($next->id, 2, $this->admin);
        $this->assertSame($legacyBytes, Storage::disk('local')->get($legacy->archivo_pdf_path));
        $this->assertSame($legacySnapshot, $legacy->fresh()->snapshot_datos);
        $this->assertArrayNotHasKey('plantilla_certificado', $legacySnapshot);
        $this->assertSame($bytes, Storage::disk('local')->get($issued->archivo_pdf_path));
        $this->assertSame($snapshot, $issued->fresh()->snapshot_datos);
        $this->assertSame(hash('sha256', $bytes), $issued->fresh()->hash_pdf);
    }

    public function test_preview_es_pdf_sintetico_no_emite_certificados_y_funciones_son_de_ejemplo(): void
    {
        $version = $this->draft();
        $before = DB::table('certificados')->count();
        $response = $this->postJson($this->root.'/versiones/'.$version->id.'/preview', ['escenario' => 'breve'])->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Template-Preview', 'synthetic');
        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Persona', $text);
        $this->assertStringContainsString('DATOS SINTÉTICOS', $text);
        $this->assertStringContainsString('Función sintética 1', $text);
        $this->assertSame($before, DB::table('certificados')->count());
        $this->assertSame(0, DB::table('solicitudes_certificacion')->count());
    }

    public function test_render_usa_solo_snapshot_con_numeracion_texto_grupos_y_sin_consultas(): void
    {
        $version = $this->draft();
        $snapshot = $this->snapshot($version);
        $snapshot['manual_funciones']['funciones'] = [
            ['orden' => 1, 'numero_fuente' => 7, 'grupo' => 'Grupo sintético A', 'descripcion' => 'Primera función <literal> íntegra.'],
            ['orden' => 2, 'numero_fuente' => null, 'grupo' => 'Grupo sintético B', 'descripcion' => 'Segunda función, sin número de fuente.'],
        ];
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        $html = app(CertificateTemplateRenderer::class)->html($snapshot);
        $this->assertSame(0, $queries);
        $this->assertStringContainsString('7. Primera función &lt;literal&gt; íntegra.', $html);
        $this->assertStringContainsString('Grupo sintético B', $html);
        $this->assertStringNotContainsString('2. Segunda función', $html);
        $this->assertLessThan(strpos($html, 'Segunda función'), strpos($html, 'Primera función'));
    }

    public function test_snapshot_de_plantilla_congelado_no_cambia_tras_otra_publicacion(): void
    {
        $version = $this->draft();
        app(CertificateTemplateService::class)->publish($version->id, 1, $this->admin);
        $snapshot = $this->snapshot($version);
        $before = app(CertificateTemplateRenderer::class)->html($snapshot);
        $next = $this->draft();
        $content = $next->contenido;
        $content['title'] = 'OTRA VERSIÓN';
        $next = app(CertificateTemplateService::class)->save($next->id, 1, $content, $this->admin);
        app(CertificateTemplateService::class)->publish($next->id, 2, $this->admin);
        $this->assertSame($before, app(CertificateTemplateRenderer::class)->html($snapshot));
        $this->assertSame($next->id, app(CertificateTemplateService::class)->forSnapshot('funciones')['id']);
    }

    public function test_dato_opcional_ausente_omite_parrafo_y_dato_obligatorio_bloquea(): void
    {
        $version = $this->draft();
        $content = $version->contenido;
        $content['paragraphs'][] = ['id' => 'retiro', 'position' => 'body', 'alignment' => 'left', 'runs' => [['type' => 'text', 'text' => 'Finalizó el '], ['type' => 'variable', 'variable' => 'fecha_retiro']]];
        $version = app(CertificateTemplateService::class)->save($version->id, 1, $content, $this->admin);
        $snapshot = $this->snapshot($version);
        $this->assertStringNotContainsString('Finalizó el', app(CertificateTemplateRenderer::class)->html($snapshot));
        $snapshot['funcionario']['numero_documento'] = null;
        $this->expectException(CertificateTemplateException::class);
        app(CertificateTemplateRenderer::class)->html($snapshot);
    }

    public function test_sencillo_pdf_no_imprime_funciones_ni_salario_y_furniture_se_repite(): void
    {
        $version = $this->draft('sencillo');
        $snapshot = $this->snapshot($version);
        $snapshot['manual_funciones'] = ['funciones' => [['descripcion' => 'No debe aparecer']]];
        $snapshot['salario'] = 9999999;
        $pdf = app(CertificateTemplateRenderer::class)->generar($snapshot);
        $pages = (new Parser)->parseContent($pdf)->getPages();
        $this->assertCount(1, $pages);
        $this->assertStringNotContainsString('No debe aparecer', $pages[0]->getText());
        $this->assertStringNotContainsString('9999999', $pages[0]->getText());
        $this->assertStringContainsString('892.099.324-3', $pages[0]->getText());
    }

    public function test_funciones_extensas_pdf_repite_borrador_qr_pie_y_paginas(): void
    {
        $version = $this->draft();
        $pdf = app(CertificateTemplateRenderer::class)->generar($this->snapshot($version, 'extenso'));
        $pages = (new Parser)->parseContent($pdf)->getPages();
        $this->assertGreaterThanOrEqual(5, count($pages));
        foreach ($pages as $index => $page) {
            $text = $page->getText();
            $this->assertStringContainsString('BORRADOR', $text);
            $this->assertStringContainsString('SIN VALIDEZ OFICIAL', $text);
            $this->assertStringContainsString('Espacio de verificación reservado', $text);
            $this->assertStringContainsString('892.099.324-3', $text);
            $this->assertStringContainsString('Página '.($index + 1).' de '.count($pages), $text);
        }
    }

    public static function fonts(): array
    {
        return array_map(fn ($font) => [$font], CertificateTemplateDefinition::FONTS);
    }

    #[DataProvider('fonts')]
    public function test_fuentes_permitidas_compatibles_con_pdf(string $font): void
    {
        $version = $this->draft('sencillo');
        $content = $version->contenido;
        $content['typography']['font'] = $font;
        $version = app(CertificateTemplateService::class)->save($version->id, 1, $content, $this->admin);
        $pdf = app(CertificateTemplateRenderer::class)->generar($this->snapshot($version));
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('Persona', (new Parser)->parseContent($pdf)->getText());
    }
}
