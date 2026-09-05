<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncionVersion;
use App\Models\ParametroSistema;
use App\Models\User;
use App\Services\ExpedirCertificacionService;
use App\Services\ImportarManualFuncionesService;
use App\Services\ResolverFuncionesFuncionarioService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualImportTest extends TestCase
{
    use DatabaseTransactions;

    private string $archivo;

    private array $records;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $source = json_decode(file_get_contents(database_path('data/manual_funciones_decreto_015_2023.json')), true);
        $this->records = array_values(array_filter($source, fn ($r) => in_array($r['perfil_id'], ['MF-0001', 'MF-0228', 'MF-0229'])));
        $this->archivo = tempnam(sys_get_temp_dir(), 'manual-test-');
        $this->guardar();
    }

    protected function tearDown(): void
    {
        if (is_file($this->archivo)) {
            unlink($this->archivo);
        }
        parent::tearDown();
    }

    private function guardar(): void
    {
        file_put_contents($this->archivo, json_encode($this->records, JSON_UNESCAPED_UNICODE));
    }

    private function importar(bool $dryRun = false): array
    {
        return app(ImportarManualFuncionesService::class)->ejecutar($this->archivo, $dryRun);
    }

    private function version(bool $publicar = false): ManualFuncionVersion
    {
        $result = $this->importar();
        $this->assertSame([], $result['errores']);
        $version = ManualFuncionVersion::findOrFail($result['version_id']);
        if ($publicar) {
            // Synthetic test-only validity, never a claim about the real decree.
            $version->update(['estado' => 'publicado', 'vigencia_desde' => '2020-01-01', 'acto_fecha' => '2020-01-01']);
        }

        return $version;
    }

    private function empleado(string $source, string $numero, bool $demo = false): User
    {
        $ficha = ManualCargoVersion::where('source_id', $source)->firstOrFail();
        $user = User::factory()->create(['documento' => $numero, 'estado' => true, 'must_change_password' => false]);
        $funcionario = Funcionario::create(['user_id' => $user->id, 'tipo_documento' => 'CC',
            'numero_documento' => $numero, 'nombres' => 'Prueba', 'apellidos' => $source,
            'estado' => 'activo', 'fecha_ingreso' => '2020-01-01', 'cargo_id' => $ficha->cargo_id]);
        FuncionarioCargo::create(['funcionario_id' => $funcionario->id, 'cargo_id' => $ficha->cargo_id,
            'manual_cargo_version_id' => $ficha->id, 'es_prueba_manual' => $demo,
            'fecha_inicio' => '2020-01-01', 'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa']);

        return $user;
    }

    public function test_json_real_valido_preserva_ceros_campos_y_funciones(): void
    {
        $result = $this->importar();
        $this->assertSame([], $result['errores']);
        $this->assertSame(3, $result['fichas_nuevas']);
        $this->assertDatabaseHas('cargos', ['codigo' => '005', 'grado' => '03']);
        foreach ($this->records as $r) {
            $ficha = ManualCargoVersion::where('source_id', $r['perfil_id'])->firstOrFail();
            $this->assertEquals($r, $ficha->metadata_manual);
            $this->assertSame(array_column($r['funciones'], 'texto'), $ficha->funciones->pluck('descripcion')->all());
        }
    }

    public function test_json_invalido_no_importa_y_registra_rechazo(): void
    {
        file_put_contents($this->archivo, '{invalid');
        $result = $this->importar();
        $this->assertNotEmpty($result['errores']);
        $this->assertSame(0, ManualCargoVersion::count());
        $this->assertDatabaseHas('manual_importaciones', ['resultado' => 'rechazado']);
    }

    public function test_codigo_numerico_y_orden_duplicado_se_rechazan(): void
    {
        $this->records[0]['codigo'] = 5;
        $this->records[0]['funciones'][1]['orden'] = 1;
        $this->guardar();
        $this->assertNotEmpty($this->importar()['errores']);
    }

    public function test_dry_run_no_escribe_ni_auditoria(): void
    {
        $this->assertSame([], $this->importar(true)['errores']);
        foreach (['cargos', 'manuales_funciones', 'manual_funciones_versiones', 'manual_cargo_versiones', 'manual_funciones_esenciales', 'manual_importaciones'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_segunda_importacion_es_idempotente_y_auditable(): void
    {
        $this->version();
        $ids = DB::table('manual_funciones_esenciales')->pluck('id')->all();
        $result = $this->importar();
        $this->assertSame([], $result['errores']);
        $this->assertSame(3, $result['fichas_sin_cambios']);
        $this->assertSame(0, $result['fichas_actualizadas']);
        $this->assertSame($ids, DB::table('manual_funciones_esenciales')->pluck('id')->all());
        $this->assertSame(2, Cargo::count());
        $this->assertSame(1, ManualFuncionVersion::count());
        $this->assertSame(2, DB::table('manual_importaciones')->count());
    }

    public function test_cambio_borrador_actualiza_solo_la_ficha_y_conserva_ids(): void
    {
        $this->version();
        $ficha = ManualCargoVersion::where('source_id', 'MF-0228')->sole();
        $funcionId = $ficha->funciones->first()->id;
        $otra = ManualCargoVersion::where('source_id', 'MF-0229')->sole()->content_hash;
        $this->records[1]['funciones'][0]['texto'] = 'Texto revisado en la fuente de prueba.';
        $this->guardar();
        $result = $this->importar();
        $this->assertSame([], $result['errores']);
        $this->assertSame(1, $result['fichas_actualizadas']);
        $this->assertSame($funcionId, $ficha->fresh()->funciones->first()->id);
        $this->assertSame('Texto revisado en la fuente de prueba.', $ficha->fresh()->funciones->first()->descripcion);
        $this->assertSame($otra, ManualCargoVersion::where('source_id', 'MF-0229')->sole()->content_hash);
    }

    public function test_una_candidata_no_se_asigna_durante_la_generacion(): void
    {
        $this->version(true);
        $u = $this->empleado('MF-0001', 'TEST-MANUAL-UNICA');
        $u->funcionario->historialCargos()->update(['manual_cargo_version_id' => null]);
        $this->expectExceptionMessage('MANUAL_FICHA_NO_ASIGNADA');
        app(ResolverFuncionesFuncionarioService::class)->resolver($u->funcionario, CarbonImmutable::now());
    }

    public function test_fk_impide_relacionar_ficha_con_cargo_diferente(): void
    {
        $this->version(true);
        $u = $this->empleado('MF-0228', 'TEST-MANUAL-FK');
        $alcalde = ManualCargoVersion::where('source_id', 'MF-0001')->sole();
        $this->expectException(QueryException::class);
        $u->funcionario->historialCargos()->update(['manual_cargo_version_id' => $alcalde->id]);
    }

    public function test_fuente_incompleta_no_se_publica_y_endpoint_legado_no_elige_ficha(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $version = $this->version();
        $admin = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/manual-funciones/versiones/'.$version->id.'/publicar')
            ->assertConflict()->assertJsonPath('code', 'MANUAL_FECHAS_PENDIENTES');
        $ficha = ManualCargoVersion::where('source_id', 'MF-0228')->sole();
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/manual-funciones/versiones/'.$version->id.'/cargos/'.$ficha->cargo_id, [
            'proposito_principal' => 'Cambio no permitido', 'funciones' => [['orden' => 1, 'descripcion' => 'Cambio']],
        ])->assertConflict()->assertJsonPath('code', 'MANUAL_FICHA_AMBIGUA');
    }

    public function test_publicada_admite_noop_pero_rechaza_cambios_y_fichas_omitidas(): void
    {
        $this->version(true);
        $this->assertSame([], $this->importar()['errores']);
        $this->records[1]['funciones'][0]['texto'] = 'Contenido distinto';
        $this->guardar();
        $this->assertContains('MANUAL_VERSION_IMMUTABLE', $this->importar()['errores']);
        array_pop($this->records);
        $this->guardar();
        $this->assertNotEmpty($this->importar()['errores']);
        $this->assertSame(3, ManualCargoVersion::count());
    }

    public function test_source_id_duplicado_y_renumeracion_se_rechazan(): void
    {
        $this->version();
        $this->records[0]['area_funcional'] = 'Otra área';
        $this->guardar();
        $this->assertStringContainsString('MANUAL_SOURCE_ID_REASIGNADO', implode(' ', $this->importar()['errores']));
        $this->records[] = $this->records[0];
        $this->guardar();
        $this->assertNotEmpty($this->importar()['duplicados']);
    }

    public function test_mismo_codigo_grado_resuelve_dos_fichas_sin_mezcla_y_snapshot_pdf(): void
    {
        $this->version(true);
        $a = $this->empleado('MF-0228', 'TEST-MANUAL-A');
        $b = $this->empleado('MF-0229', 'TEST-MANUAL-B');
        ParametroSistema::create(['clave' => 'requiere_pago_certificado', 'valor' => 'false', 'tipo' => 'boolean']);
        $resolver = app(ResolverFuncionesFuncionarioService::class);
        $fa = $resolver->resolver($a->funcionario, CarbonImmutable::now());
        $fb = $resolver->resolver($b->funcionario, CarbonImmutable::now());
        $this->assertSame($fa['cargo_id'], $fb['cargo_id']);
        $this->assertNotSame($fa['ficha_id'], $fb['ficha_id']);
        $this->assertNotSame($fa['funciones'], $fb['funciones']);
        $pdfs = [];
        foreach ([$a, $b] as $user) {
            $result = app(ExpedirCertificacionService::class)->expedir($user, false, 'funciones', null);
            $cert = $result['certificado'];
            $snapshot = $cert->snapshot_datos;
            $ficha = ManualCargoVersion::findOrFail($snapshot['manual']['ficha_id']);
            $this->assertSame($ficha->manual_funciones_version_id, $snapshot['manual']['version_id']);
            $this->assertSame($ficha->funciones->pluck('descripcion')->all(), array_column($snapshot['funciones_especificas'], 'descripcion'));
            $this->assertArrayHasKey('funciones_comunes', $snapshot);
            $pdf = Storage::disk('local')->get($cert->archivo_pdf_path);
            $this->assertStringContainsString($ficha->source_id, $pdf);
            $this->assertStringContainsString('CERTIFICADO LABORAL TEMPORAL', $pdf);
            $this->assertStringContainsString(iconv('UTF-8', 'Windows-1252', mb_substr($ficha->funciones->first()->descripcion, 0, 35)), $pdf);
            $ficha->funciones->first()->update(['descripcion' => 'Cambio posterior para probar snapshot']);
            $this->assertSame($snapshot, $cert->fresh()->snapshot_datos);
            $this->assertSame($pdf, Storage::disk('local')->get($cert->archivo_pdf_path));
            $pdfs[] = $pdf;
        }
        $this->assertNotSame($pdfs[0], $pdfs[1]);
    }

    public function test_dos_fichas_sin_asignacion_explicita_no_elige_primera(): void
    {
        $this->version(true);
        $u = $this->empleado('MF-0228', 'TEST-MANUAL-A');
        $u->funcionario->historialCargos()->update(['manual_cargo_version_id' => null]);
        $this->expectExceptionMessage('MANUAL_FICHA_AMBIGUA');
        app(ResolverFuncionesFuncionarioService::class)->resolver($u->funcionario, CarbonImmutable::now());
    }

    public function test_cero_fichas_validas_no_genera_pdf(): void
    {
        $this->version();
        $u = $this->empleado('MF-0228', 'TEST-MANUAL-A');
        $u->funcionario->historialCargos()->update(['manual_cargo_version_id' => null]);
        $this->expectExceptionMessage('MANUAL_FICHA_NO_ENCONTRADA');
        app(ResolverFuncionesFuncionarioService::class)->resolver($u->funcionario, CarbonImmutable::now());
    }

    public function test_borrador_solo_permite_funcionario_marcado_de_desarrollo(): void
    {
        $this->version();
        $demo = $this->empleado('MF-0229', 'TEST-MANUAL-D', true);
        $this->assertTrue(app(ResolverFuncionesFuncionarioService::class)->resolver($demo->funcionario, CarbonImmutable::now())['prueba_desarrollo']);
        $normal = $this->empleado('MF-0228', 'TEST-MANUAL-N');
        $this->expectExceptionMessage('MANUAL_VERSION_NO_VIGENTE');
        app(ResolverFuncionesFuncionarioService::class)->resolver($normal->funcionario, CarbonImmutable::now());
    }

    public function test_precheck_password_no_consume_cupo(): void
    {
        $this->version(true);
        $u = $this->empleado('MF-0229', 'TEST-MANUAL-C');
        $u->update(['must_change_password' => true]);
        try {
            app(ExpedirCertificacionService::class)->expedir($u, false, 'funciones', null);
            $this->fail('Debió bloquear contraseña');
        } catch (DomainException $e) {
            $this->assertSame('PASSWORD_CHANGE_REQUIRED', $e->getMessage());
        }
        $this->assertSame(0, DB::table('certificados')->count());
        $this->assertSame(0, DB::table('solicitudes_certificacion')->count());
    }

    public function test_precheck_fuentes_y_salario_devuelve_codigos_y_no_genera_pdf(): void
    {
        $version = $this->version(true);
        $u = $this->empleado('MF-0229', 'TEST-MANUAL-P');
        $asignacion = $u->funcionario->historialCargos()->sole();
        $ficha = $asignacion->fichaManual;
        $cases = [
            ['SALARIO_NO_RESOLUBLE', fn () => null, true],
            ['CARGO_INVALIDO', fn () => $asignacion->cargo->update(['estado' => false]), false],
            ['ASIGNACION_NO_VIGENTE', function () use ($asignacion) {
                $asignacion->cargo->update(['estado' => true]);
                $asignacion->update(['fecha_fin' => '2020-02-01']);
            }, false],
            ['MANUAL_VERSION_NO_VIGENTE', function () use ($asignacion, $version) {
                $asignacion->update(['fecha_fin' => null]);
                $version->update(['vigencia_hasta' => '2020-02-01']);
            }, false],
            ['MANUAL_FICHA_INCOMPLETA', function () use ($version, $ficha) {
                $version->update(['vigencia_hasta' => null]);
                $ficha->update(['area_funcional' => '']);
            }, false],
            ['MANUAL_FUNCIONES_NO_DISPONIBLES', function () use ($ficha) {
                $ficha->update(['area_funcional' => 'Riesgo']);
                $ficha->funciones()->update(['descripcion' => '']);
            }, false],
        ];
        foreach ($cases as [$expected, $prepare, $salary]) {
            $prepare();
            try {
                app(ExpedirCertificacionService::class)->expedir($u->fresh(), $salary, 'funciones', null);
                $this->fail('Debió bloquear '.$expected);
            } catch (DomainException $e) {
                $this->assertSame($expected, $e->getMessage());
            }
            $this->assertSame(0, DB::table('certificados')->count());
            $this->assertSame(0, DB::table('solicitudes_certificacion')->count());
        }
    }

    public function test_comunes_se_resuelven_separadas_sin_duplicar_especificas(): void
    {
        $v = $this->version(true);
        DB::table('manual_funciones_comunes_nivel')->insert(['manual_funciones_version_id' => $v->id,
            'nivel' => 'Técnico', 'orden' => 1, 'descripcion' => 'Función común sintética']);
        $u = $this->empleado('MF-0229', 'TEST-MANUAL-COM');
        $r = app(ResolverFuncionesFuncionarioService::class)->resolver($u->funcionario, CarbonImmutable::now());
        $this->assertCount(6, $r['funciones_especificas']);
        $this->assertSame('Función común sintética', $r['funciones_comunes'][0]['descripcion']);
        $this->assertFalse($r['imprimir_funciones_comunes']);
    }

    public function test_autoservicio_http_usa_identidad_autenticada_y_ficha_asignada(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $this->version(true);
        foreach (['MF-0228', 'MF-0229'] as $i => $source) {
            $u = $this->empleado($source, 'TEST-MANUAL-HTTP'.$i);
            $u->assignRole('funcionario');
            $response = $this->actingAs($u, 'sanctum')->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => 'funciones', 'requiere_salario' => false,
            ])->assertCreated()->assertJsonPath('data.resultado', 'generada');
            $cert = Certificado::where('funcionario_id', $u->funcionario->id)->sole();
            $this->assertSame($source, $cert->snapshot_datos['manual']['source_id']);
            $this->actingAs($u, 'sanctum')->get($response->json('data.descarga_url'))->assertOk();
            $this->actingAs($u, 'sanctum')->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => 'funciones', 'requiere_salario' => false,
            ])->assertConflict()->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');
        }
    }

    public function test_admin_seleccion_explicita_e_historial_con_mismo_cargo(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $this->version(true);
        $u = $this->empleado('MF-0228', 'TEST-MANUAL-HIST');
        $anterior = $u->funcionario->historialCargos()->sole();
        $otra = ManualCargoVersion::where('source_id', 'MF-0229')->sole();
        $admin = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/manual-funciones/fichas?cargo_id='.$otra->cargo_id)
            ->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/funcionarios/'.$u->funcionario->id, [
            'cargo_id' => $otra->cargo_id, 'manual_cargo_version_id' => $otra->id,
        ])->assertOk();
        $this->assertSame($anterior->manual_cargo_version_id, $anterior->fresh()->manual_cargo_version_id);
        $this->assertSame(today()->subDay()->toDateString(), $anterior->fresh()->fecha_fin->toDateString());
        $resolver = app(ResolverFuncionesFuncionarioService::class);
        $this->assertSame('MF-0228', $resolver->resolver($u->funcionario, CarbonImmutable::now()->subDay())['source_id']);
        $this->assertSame('MF-0229', $resolver->resolver($u->funcionario, CarbonImmutable::now())['source_id']);
    }

    public function test_api_rechaza_ficha_otro_cargo_y_campo_prueba_inyectado(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $this->version(true);
        $u = $this->empleado('MF-0228', 'TEST-MANUAL-API');
        $admin = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $admin->assignRole('admin');
        $ficha = ManualCargoVersion::where('source_id', 'MF-0001')->sole();
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/funcionarios/'.$u->funcionario->id, [
            'manual_cargo_version_id' => $ficha->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('manual_cargo_version_id');
        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/funcionarios/'.$u->funcionario->id, [
            'es_prueba_manual' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('es_prueba_manual');
    }
}
