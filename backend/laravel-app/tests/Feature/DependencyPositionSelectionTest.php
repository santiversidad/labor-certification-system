<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\User;
use App\Services\ResolverFuncionesFuncionarioService;
use App\Services\SeleccionarFichaManualService;
use App\Services\UnidadesManualService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DependencyPositionSelectionTest extends TestCase
{
    use DatabaseTransactions;

    private $version;

    private Cargo $cargo;

    private ManualCargoVersion $a;

    private ManualCargoVersion $b;

    private string $unidadA;

    private string $unidadB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $admin = User::factory()->backoffice()->create(['estado' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');
        $this->cargo = Cargo::create(['codigo' => 'DPS', 'grado' => '02', 'denominacion' => 'Profesional sintético', 'nivel' => 'Profesional', 'estado' => true]);
        $manual = ManualFuncion::create(['codigo' => 'DPS', 'nombre' => 'Manual sintético de dependencias']);
        $this->version = $manual->versiones()->create(['version' => 'DPS-1', 'estado' => 'borrador',
            'vigencia_desde' => '2020-01-01', 'acto_tipo' => 'Prueba', 'acto_numero' => 'DPS', 'acto_fecha' => '2020-01-01']);
        $this->a = $this->ficha('Unidad A', 'DPS-A');
        $this->b = $this->ficha('Unidad B', 'DPS-B');
        $this->unidadA = app(UnidadesManualService::class)->unidadFicha($this->a->id)['id'];
        $this->unidadB = app(UnidadesManualService::class)->unidadFicha($this->b->id)['id'];
    }

    private function ficha(string $area, string $source, ?Cargo $cargo = null, bool $completa = true): ManualCargoVersion
    {
        $ficha = $this->version->cargos()->create(['cargo_id' => ($cargo ?? $this->cargo)->id, 'source_id' => $source,
            'dependencia' => 'Donde se ubique el cargo', 'area_funcional' => $area, 'proposito_principal' => $completa ? 'Probar '.$area : '']);
        if ($completa) {
            $ficha->funciones()->create(['orden' => 1, 'descripcion' => 'Función de '.$area]);
        }
        \Tests\Support\ManualFixture::documentar($ficha);

        return $ficha;
    }

    private function publicar(): void
    {
        $this->version->update(['estado' => 'publicado']);
    }

    private function publicarAmbiguedadAdicional(): void
    {
        // La nueva condición se publica desde un borrador sintético independiente:
        // nunca se agregan fichas al contenido normativo ya publicado.
        $manual = ManualFuncion::create(['codigo' => 'DPS-ADICIONAL', 'nombre' => 'Manual sintético adicional']);
        $this->version = $manual->versiones()->create(['version' => 'DPS-ADICIONAL-1', 'estado' => 'borrador',
            'vigencia_desde' => '2020-01-01', 'acto_tipo' => 'Prueba', 'acto_numero' => 'DPS-ADICIONAL', 'acto_fecha' => '2020-01-01']);
        $this->ficha('Unidad A', 'DPS-NUEVA-AMBIGUA');
        $this->publicar();
    }

    private function datos(string $unidad): array
    {
        return ['tipo_documento' => 'CC', 'numero_documento' => 'DPS123456', 'nombres' => 'Persona', 'apellidos' => 'Sintética',
            'fecha_ingreso' => '2024-01-15', 'estado' => 'activo', 'cargo_id' => $this->cargo->id, 'unidad_organizacional_id' => $unidad,
            'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa'];
    }

    public function test_catalogo_y_cargos_se_filtran_en_servidor_por_unidad_y_busqueda(): void
    {
        $soloA = Cargo::create(['codigo' => 'DPA', 'grado' => '09', 'denominacion' => 'Exclusivo A', 'nivel' => 'Profesional', 'estado' => true]);
        $this->ficha('Unidad A', 'DPS-A2', $soloA);
        $this->publicar();
        $this->getJson('/api/v1/manual-funciones/dependencias?fecha=2024-01-15&q=Unidad%20A')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/manual-funciones/dependencias/cargos?fecha=2024-01-15&unidad_organizacional_id='.$this->unidadA)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/manual-funciones/dependencias/cargos?fecha=2024-01-15&unidad_organizacional_id='.$this->unidadB)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->cargo->id);
        foreach (['DPA', '09', 'Exclusivo'] as $q) {
            $this->getJson('/api/v1/manual-funciones/dependencias/cargos?fecha=2024-01-15&unidad_organizacional_id='.$this->unidadA.'&q='.$q)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $soloA->id);
        }
    }

    public function test_cargo_identico_se_desambigua_por_dependencia_y_funciones_usan_ficha_exacta(): void
    {
        $this->publicar();
        $created = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadB))->assertCreated();
        $empleado = Funcionario::findOrFail($created->json('data.id'));
        $this->assertSame($this->b->id, $empleado->historialCargos()->sole()->manual_cargo_version_id);
        $this->assertSame('DPS-B', app(ResolverFuncionesFuncionarioService::class)->resolver($empleado, CarbonImmutable::now())['source_id']);
        $this->assertSame($this->a->id, app(SeleccionarFichaManualService::class)->porUnidad($this->unidadA, $this->cargo->id, CarbonImmutable::now())->id);
    }

    public function test_unidad_cargo_incorrectos_y_ficha_de_otra_unidad_son_rechazados(): void
    {
        $otro = Cargo::create(['codigo' => 'DPB', 'grado' => '09', 'denominacion' => 'Exclusivo B', 'nivel' => 'Profesional', 'estado' => true]);
        $this->ficha('Unidad B', 'DPS-B2', $otro);
        $this->publicar();
        $this->postJson('/api/v1/funcionarios', array_replace($this->datos($this->unidadA), ['cargo_id' => $otro->id]))->assertUnprocessable()->assertJsonValidationErrors('cargo_id');
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA) + ['manual_cargo_version_id' => $this->b->id])->assertUnprocessable()->assertJsonValidationErrors('manual_cargo_version_id');
        $this->postJson('/api/v1/funcionarios', $this->datos(str_repeat('0', 32)))->assertUnprocessable();
        $this->assertDatabaseMissing('funcionarios', ['numero_documento' => 'DPS123456']);
    }

    public function test_ambiguedad_documentada_se_resuelve_con_seleccion_explicita_compatible(): void
    {
        $this->ficha('Unidad A', 'DPS-AMBIGUA');
        $this->publicar();
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA) + ['manual_cargo_version_id' => $this->a->id])->assertCreated();
    }

    public function test_resolver_expone_codigos_distintos_sin_candidatos_ni_funciones_completas(): void
    {
        $this->publicar();
        $url = '/api/v1/manual-funciones/dependencias/resolver?fecha=2024-01-15&unidad_organizacional_id='.$this->unidadA.'&cargo_id='.$this->cargo->id;
        $this->getJson($url)->assertOk()->assertExactJson(['success' => true, 'message' => 'Operación realizada correctamente.', 'data' => ['source_id' => 'DPS-A', 'funciones_count' => 1]]);
        $this->publicarAmbiguedadAdicional();
        $response = $this->getJson($url)->assertUnprocessable()
            ->assertJsonPath('code', 'MANUAL_FICHA_AMBIGUA')->assertJsonValidationErrors('cargo_id');
        $this->getJson($url.'&manual_cargo_version_id='.$this->a->id)->assertOk()->assertJsonPath('data.manual_cargo_version_id', $this->a->id);
        $this->assertSame(['success', 'message', 'errors', 'code'], array_keys($response->json()));
        $this->getJson(str_replace($this->unidadA, str_repeat('0', 32), $url))->assertUnprocessable()
            ->assertJsonPath('code', 'MANUAL_FICHA_NO_ENCONTRADA')->assertJsonValidationErrors('cargo_id');
    }

    public function test_fecha_de_vinculacion_y_estado_de_version_continuan_aplicando(): void
    {
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertUnprocessable();
        $this->publicar();
        $this->postJson('/api/v1/funcionarios', array_replace($this->datos($this->unidadA), ['fecha_ingreso' => '2019-01-01']))->assertUnprocessable();
        $this->getJson('/api/v1/manual-funciones/dependencias?fecha=2019-01-01&q=Unidad')->assertOk()->assertJsonCount(0, 'data');
        $this->version->update(['vigencia_hasta' => '2023-12-31']);
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertUnprocessable();
    }

    public function test_cambio_de_unidad_conserva_historico_y_cambia_ficha_desde_hoy(): void
    {
        $this->publicar();
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertCreated()->json('data.id');
        $anterior = Funcionario::find($id)->historialCargos()->sole();
        $this->putJson('/api/v1/funcionarios/'.$id, ['unidad_organizacional_id' => $this->unidadB, 'cargo_id' => $this->cargo->id])->assertOk();
        $this->assertSame($this->a->id, $anterior->fresh()->manual_cargo_version_id);
        $empleado = Funcionario::find($id);
        $this->assertSame(2, $empleado->historialCargos()->count());
        $resolver = app(ResolverFuncionesFuncionarioService::class);
        $this->assertSame('DPS-A', $resolver->resolver($empleado, CarbonImmutable::yesterday())['source_id']);
        $this->assertSame('DPS-B', $resolver->resolver($empleado, CarbonImmutable::today())['source_id']);
    }

    public function test_edicion_personal_legacy_no_reinterpreta_ficha_ni_historico(): void
    {
        $this->publicar();
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertCreated()->json('data.id');
        $asignacion = Funcionario::find($id)->historialCargos()->sole();
        $asignacion->update(['unidad_organizacional_id' => null]);
        $antes = $asignacion->fichasNormativas()->sole()->getAttributes();
        $this->putJson('/api/v1/funcionarios/'.$id, ['nombres' => 'Actualizado'])->assertOk();
        $this->getJson('/api/v1/funcionarios/'.$id)->assertOk()->assertJsonPath('data.vinculacion_legacy', true)->assertJsonPath('data.unidad_organizacional.id', $this->unidadA);
        $this->assertSame($antes, $asignacion->fichasNormativas()->sole()->getAttributes());
        $this->assertSame(1, Funcionario::find($id)->historialCargos()->count());
    }

    public function test_edicion_personal_conserva_ficha_exacta_aunque_catalogo_sea_ambiguo(): void
    {
        $this->publicar();
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertCreated()->json('data.id');
        $asignacion = Funcionario::find($id)->historialCargos()->sole();
        $antes = $asignacion->fichasNormativas()->sole()->getAttributes();
        $this->publicarAmbiguedadAdicional();
        $this->putJson('/api/v1/funcionarios/'.$id, ['nombres' => 'Actualizado'])->assertOk();
        $this->getJson('/api/v1/funcionarios/'.$id)->assertOk()->assertJsonPath('data.asignacion_actual.ficha_manual.source_id', 'DPS-A');
        $this->assertSame($antes, $asignacion->fichasNormativas()->sole()->getAttributes());
        $this->assertSame(1, Funcionario::find($id)->historialCargos()->count());
    }

    public function test_perfil_incompleto_no_bloquea_sencillo(): void
    {
        $inc = $this->ficha('Unidad incompleta', 'DPS-INC', completa: false);
        $this->publicar();
        $unidad = app(UnidadesManualService::class)->unidadFicha($inc->id)['id'];
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($unidad))->assertCreated()->json('data.id');
        $user = Funcionario::find($id)->user;
        $user->update(['must_change_password' => false]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo'])->assertCreated();
        $this->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'funciones'])->assertConflict()->assertJsonPath('code', 'MANUAL_FICHA_INCOMPLETA');
    }

    public function test_normalizacion_conservadora_no_inventa_unidades_ni_modifica_fuente(): void
    {
        $ficha = $this->ficha(' Unidad A ', 'DPS-SPACES');
        $this->ficha('Todas las dependencias', 'DPS-GLOBAL');
        $sinArea = $this->ficha('', 'DPS-SIN-AREA');
        $this->publicar();
        $this->assertSame($this->unidadA, app(UnidadesManualService::class)->unidadFicha($ficha->id)['id']);
        $this->assertNull(app(UnidadesManualService::class)->unidadFicha($sinArea->id));
        $this->getJson('/api/v1/manual-funciones/dependencias?fecha=2024-01-15&q=Todas')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(' Unidad A ', $ficha->fresh()->area_funcional);
    }

    public function test_autoservicio_no_accede_a_selectores_ni_modifica_relacion(): void
    {
        $this->publicar();
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertCreated()->json('data.id');
        $user = Funcionario::find($id)->user;
        $user->update(['must_change_password' => false]);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/manual-funciones/dependencias?fecha=2024-01-15')->assertForbidden();
        $this->getJson('/api/v1/manual-funciones/dependencias/cargos?fecha=2024-01-15&unidad_organizacional_id='.$this->unidadA)->assertForbidden();
        $this->putJson('/api/v1/funcionarios/'.$id, ['unidad_organizacional_id' => $this->unidadB])->assertForbidden();
    }

    public function test_perfil_de_todas_las_dependencias_es_aplicable_sin_inventar_unidad(): void
    {
        $general = Cargo::create(['codigo' => 'DPG', 'grado' => '01', 'denominacion' => 'Cargo transversal', 'nivel' => 'Profesional', 'estado' => true]);
        $ficha = $this->ficha('Todas las dependencias', 'DPS-GLOBAL', $general);
        $this->publicar();
        foreach ([$this->unidadA, $this->unidadB] as $unidad) {
            $this->assertSame($ficha->id, app(SeleccionarFichaManualService::class)->porUnidad($unidad, $general->id, CarbonImmutable::now())->id);
        }
        $created = $this->postJson('/api/v1/funcionarios', array_replace($this->datos($this->unidadB), ['cargo_id' => $general->id]))->assertCreated()
            ->assertJsonPath('data.dependencia', 'Unidad B')->assertJsonPath('data.unidad_organizacional.id', $this->unidadB);
        $id = $created->json('data.id');
        $this->getJson('/api/v1/funcionarios/'.$id)->assertOk()->assertJsonPath('data.unidad_organizacional.id', $this->unidadB);
        $this->assertSame('DPS-GLOBAL', app(ResolverFuncionesFuncionarioService::class)->resolver(Funcionario::find($id), CarbonImmutable::now())['source_id']);
        $this->getJson('/api/v1/manual-funciones/dependencias?fecha=2024-01-15&q=Todas')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/funcionarios', array_replace($this->datos(str_repeat('0', 32)), ['cargo_id' => $general->id]))->assertUnprocessable();
    }

    public function test_perfil_global_y_especifico_coincidentes_son_ambiguos(): void
    {
        $this->ficha('Todas las dependencias', 'DPS-GLOBAL-AMBIGUO');
        $this->publicar();
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertUnprocessable()->assertJsonValidationErrors('cargo_id');
    }

    public function test_payloads_no_pueden_borrar_dependencia_ni_enviar_cargo_como_arreglo(): void
    {
        $this->publicar();
        $this->postJson('/api/v1/funcionarios', array_replace($this->datos($this->unidadA), ['cargo_id' => [$this->cargo->id]]))->assertUnprocessable()->assertJsonValidationErrors('cargo_id');
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertCreated()->json('data.id');
        $this->putJson('/api/v1/funcionarios/'.$id, ['dependencia' => null, 'nombres' => 'Actualizado'])->assertOk()->assertJsonPath('data.dependencia', 'Unidad A');
        $this->putJson('/api/v1/funcionarios/'.$id, ['dependencia' => 'Unidad inventada'])->assertUnprocessable()->assertJsonValidationErrors('dependencia');
    }

    public function test_perfiles_repetidos_paginados_y_busqueda_por_pagina_sin_cargar_funciones(): void
    {
        $segunda = $this->ficha('Unidad A', 'DPS-A-SEGUNDA');
        $this->publicar();
        $url = '/api/v1/manual-funciones/dependencias/cargos?fecha=2024-01-15&perfiles=1&unidad_organizacional_id='.$this->unidadA;
        $this->getJson($url)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonMissingPath('data.0.funciones')->assertJsonPath('data.0.pagina_verificada', true);
        $this->getJson($url.'&q='.$segunda->pagina_inicio)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.manual_cargo_version_id', $segunda->id);
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertUnprocessable()->assertJsonPath('code', 'MANUAL_FICHA_AMBIGUA');
    }

    public function test_seleccion_preview_vinculo_snapshot_y_pdf_usan_solo_segunda_ficha(): void
    {
        $segunda = $this->ficha('Unidad A', 'DPS-A-SEGUNDA');
        $segunda->funciones()->update(['descripcion' => 'Texto normativo exclusivo de la segunda ficha.']);
        $segunda->funciones()->create(['orden' => 2, 'descripcion' => 'Segunda función conservada en su orden.']);
        $this->publicar();
        $url = '/api/v1/manual-funciones/dependencias/resolver?fecha=2024-01-15&funciones=1&unidad_organizacional_id='.$this->unidadA.'&cargo_id='.$this->cargo->id.'&manual_cargo_version_id='.$segunda->id;
        $this->getJson($url)->assertOk()->assertJsonPath('data.pagina_inicio', $segunda->pagina_inicio)
            ->assertJsonPath('data.funciones.0.descripcion', 'Texto normativo exclusivo de la segunda ficha.')
            ->assertJsonPath('data.funciones.1.orden', 2);
        $created = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA) + ['manual_cargo_version_id' => $segunda->id])->assertCreated();
        $employee = Funcionario::findOrFail($created->json('data.id'));
        $relation = $employee->historialCargos()->sole()->fichasNormativas()->sole();
        $this->assertSame($segunda->id, $relation->manual_cargo_version_id);
        $this->assertSame($segunda->pagina_inicio, $relation->metadata['pagina_inicio']);
        $this->getJson('/api/v1/manual-funciones/dependencias/funciones-registradas/'.$segunda->id.'?funcionario_id='.$employee->id)
            ->assertOk()->assertJsonPath('data.source_id', $segunda->source_id);
        $this->getJson('/api/v1/manual-funciones/dependencias/funciones-registradas/'.$this->b->id.'?funcionario_id='.$employee->id)->assertNotFound();
        $employee->user->update(['must_change_password' => false]);
        \Illuminate\Support\Facades\Storage::fake('local');
        $cert = app(\App\Services\ExpedirCertificacionService::class)->expedir($employee->user, 'funciones', null)['certificado'];
        $this->assertSame($segunda->id, $cert->snapshot_datos['manual_funciones']['ficha_id']);
        $this->assertSame($segunda->pagina_inicio, $cert->snapshot_datos['manual_funciones']['pagina_inicio']);
        $this->assertSame($segunda->funciones->pluck('descripcion')->all(), array_column($cert->snapshot_datos['manual_funciones']['funciones'], 'descripcion'));
        $pdf = \Illuminate\Support\Facades\Storage::disk('local')->get($cert->archivo_pdf_path);
        $text = preg_replace('/\s+/u', ' ', (new \Smalot\PdfParser\Parser)->parseContent($pdf)->getText());
        $this->assertStringContainsString('Texto normativo exclusivo de la segunda ficha.', $text);
        $this->assertStringContainsString('Segunda función conservada en su orden.', $text);
        $this->assertStringNotContainsString('Función de Unidad B', $text);
        $this->assertSame('Función de Unidad A', $this->a->funciones->sole()->descripcion);
    }

    public function test_pagina_manipulada_y_evidencia_ausente_no_habilitan_seleccion(): void
    {
        $this->a->forceFill(['pagina_inicio' => null, 'verificacion_documental' => null])->save();
        $this->publicar();
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA) + ['manual_cargo_version_id' => $this->a->id, 'pagina_inicio' => 665])
            ->assertUnprocessable()->assertJsonValidationErrors('pagina_inicio');
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA) + ['manual_cargo_version_id' => $this->a->id])
            ->assertUnprocessable()->assertJsonPath('code', 'MANUAL_FICHA_NO_VERIFICADA');
        $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertUnprocessable()->assertJsonPath('code', 'MANUAL_FICHA_NO_VERIFICADA');
    }

    public function test_generacion_rechaza_vinculo_existente_con_dependencia_incompatible(): void
    {
        $this->publicar();
        $id = $this->postJson('/api/v1/funcionarios', $this->datos($this->unidadA))->assertCreated()->json('data.id');
        $employee = Funcionario::findOrFail($id);
        $employee->historialCargos()->sole()->update(['unidad_organizacional_id' => $this->unidadB]);
        $this->expectExceptionMessage('MANUAL_FICHA_INCOMPATIBLE');
        app(ResolverFuncionesFuncionarioService::class)->resolver($employee, CarbonImmutable::now());
    }

    public function test_pagina_inicial_es_unica_en_cada_version(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->b->forceFill(['pagina_inicio' => $this->a->pagina_inicio])->save();
    }
}
