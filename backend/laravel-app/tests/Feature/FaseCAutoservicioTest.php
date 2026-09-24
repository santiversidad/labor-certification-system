<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoVinculacionEnum;
use App\Models\Cargo;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ParametroSistema;
use App\Models\User;
use App\Services\CertificadoPdfService;
use App\Services\ResolverFuncionesFuncionarioService;
use App\Services\TokenValidacionService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\Support\ManualFixture;
use Tests\TestCase;

class FaseCAutoservicioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cargo $cargo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30 03:00:00', 'America/Bogota'));

        $this->admin = User::factory()->create(['estado' => true, 'must_change_password' => false]);
        $this->admin->assignRole(RoleEnum::Admin->value);
        $this->cargo = Cargo::create([
            'codigo' => '219', 'grado' => '02', 'denominacion' => 'Profesional Universitario',
            'nivel' => 'Profesional', 'dependencia' => 'Talento Humano', 'estado' => true,
        ]);
        ParametroSistema::create([
            'clave' => 'requiere_pago_certificado', 'valor' => 'false', 'tipo' => 'boolean',
        ]);
        ManualFixture::ficha($this->cargo);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_admin_crea_funcionario_usuario_rol_y_asignacion_atomicamente(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/funcionarios', $this->datosFuncionario('100200300'))
            ->assertCreated()
            ->assertJsonPath('data.usuario.must_change_password', true)
            ->assertJsonPath('data.cargo.id', $this->cargo->id);

        $user = User::where('documento', '100200300')->firstOrFail();
        $this->assertTrue(Hash::check('100200300', $user->password));
        $this->assertNotSame('100200300', $user->password);
        $this->assertTrue($user->hasRole(RoleEnum::Funcionario->value));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseHas('funcionario_cargo', ['funcionario_id' => $user->funcionario->id, 'cargo_id' => $this->cargo->id]);
    }

    public function test_alta_de_funcionario_no_cambia_identidad_ni_permisos_del_admin(): void
    {
        $adminId = $this->admin->id;
        $this->assertCount(36, $this->admin->getAllPermissions());

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/funcionarios', $this->datosFuncionario('100200390'))
            ->assertCreated();

        $nuevo = User::where('documento', '100200390')->firstOrFail();
        $this->assertNotSame($adminId, $nuevo->id);
        $this->assertTrue($nuevo->hasRole(RoleEnum::Funcionario->value));
        $this->assertSame($nuevo->id, $nuevo->funcionario->user_id);

        $admin = $this->admin->fresh();
        $this->assertSame($adminId, $admin->id);
        $this->assertTrue($admin->hasRole(RoleEnum::Admin->value));
        $this->assertFalse($admin->hasRole(RoleEnum::Funcionario->value));
        $this->assertCount(36, $admin->getAllPermissions());
        $this->assertTrue($admin->can('funcionarios.ver'));
        $this->assertNull($admin->funcionario);
    }

    public function test_documento_del_admin_no_puede_reutilizarse_para_crear_funcionario(): void
    {
        $adminId = $this->admin->id;
        $usuariosAntes = User::count();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/funcionarios', $this->datosFuncionario($this->admin->documento))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_documento');

        $admin = $this->admin->fresh();
        $this->assertSame($usuariosAntes, User::count());
        $this->assertSame($adminId, $admin->id);
        $this->assertTrue($admin->hasRole(RoleEnum::Admin->value));
        $this->assertFalse($admin->hasRole(RoleEnum::Funcionario->value));
        $this->assertCount(36, $admin->getAllPermissions());
        $this->assertTrue($admin->can('funcionarios.ver'));
        $this->assertNull($admin->funcionario);
        $this->assertDatabaseMissing('funcionarios', ['user_id' => $adminId]);
    }

    public function test_primer_login_bloquea_funciones_hasta_cambiar_password(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/funcionarios', $this->datosFuncionario('100200301'))->assertCreated();
        $user = User::where('documento', '100200301')->firstOrFail();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/change-password', [
            'current_password' => '100200301',
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NuevaClave2026', $user->fresh()->password));
        $this->assertFalse(Hash::check('100200301', $user->fresh()->password));
        $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/v1/mi-certificacion/disponibilidad')->assertOk();
    }

    public function test_password_nueva_igual_a_cedula_es_rechazada(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200302', true);
        $this->actingAs($user, 'sanctum')->putJson('/api/v1/auth/change-password', [
            'current_password' => '100200302',
            'password' => '100200302',
            'password_confirmation' => '100200302',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_pago_desactivado_genera_y_permite_descarga_inmediata_solo_al_titular(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200303');
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'sencillo',
        ])->assertCreated()
            ->assertJsonPath('data.resultado', 'generada')
            ->assertJsonPath('data.solicitud.estado', 'generada')
            ->assertJsonStructure(['data' => ['certificado', 'descarga_url', 'descarga_expira_en']]);

        $url = $response->json('data.descarga_url');
        $this->actingAs($user, 'sanctum')->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');

        [$otro] = $this->crearFuncionarioDirecto('100200304');
        $this->actingAs($otro, 'sanctum')->getJson($url)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['accion' => 'descargar_certificado_autoservicio']);
    }

    public function test_sencillo_no_invoca_resolucion_de_funciones(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200317');
        $this->partialMock(ResolverFuncionesFuncionarioService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('resolver');
        });

        $this->solicitar($user, 'sencillo')->assertCreated();
        $this->assertArrayNotHasKey('manual_funciones', Certificado::sole()->snapshot_datos);
    }

    public function test_cupos_son_independientes_y_segunda_modalidad_igual_se_bloquea(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200305');
        $this->solicitar($user, 'sencillo')->assertCreated();
        $this->solicitar($user, 'sencillo')->assertConflict()->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');
        $this->solicitar($user, 'funciones')->assertCreated()->assertJsonPath('data.resultado', 'generada');
        $this->solicitar($user, 'funciones')->assertConflict();
        $this->assertDatabaseCount('certificados', 2);
    }

    public function test_pago_activado_crea_orden_pendiente_y_no_genera_certificado(): void
    {
        ParametroSistema::where('clave', 'requiere_pago_certificado')->update(['valor' => 'true']);
        [$user] = $this->crearFuncionarioDirecto('100200306');

        $this->solicitar($user)->assertCreated()
            ->assertJsonPath('data.resultado', 'pendiente_pago')
            ->assertJsonPath('data.orden_pago.estado', 'pendiente')
            ->assertJsonMissingPath('data.certificado');

        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseCount('ordenes_pago_certificado', 1);
    }

    public function test_inactivo_no_expide_y_admin_configura_pago_con_auditoria(): void
    {
        [$user, $funcionario] = $this->crearFuncionarioDirecto('100200307');
        $funcionario->update(['estado' => EstadoFuncionarioEnum::Retirado]);
        $this->solicitar($user)->assertForbidden()->assertJsonPath('code', 'INACTIVE_EMPLOYEE');

        $this->actingAs($this->admin, 'sanctum')->patchJson('/api/v1/configuracion/certificaciones', [
            'requiere_pago_certificado' => true,
        ])->assertOk()
            ->assertJsonPath('data.requiere_pago_certificado', true);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'configurar_pago_certificado']);

        $secretario = User::factory()->create(['must_change_password' => false]);
        $secretario->assignRole(RoleEnum::Secretario->value);
        $this->actingAs($secretario, 'sanctum')->patchJson('/api/v1/configuracion/certificaciones', [
            'requiere_pago_certificado' => false,
        ])->assertForbidden();
    }

    public function test_reset_revoca_tokens_y_reactiva_cambio_obligatorio(): void
    {
        [$user, $funcionario] = $this->crearFuncionarioDirecto('100200308');
        $user->createToken('sesion');

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/v1/funcionarios/{$funcionario->id}/restablecer-acceso")
            ->assertOk();

        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('100200308', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'restablecer_acceso_funcionario']);
    }

    public function test_fallo_pdf_revierte_todo_y_permite_reintentar(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200309');
        $this->mock(CertificadoPdfService::class, function ($mock) {
            $mock->shouldReceive('generarDesdeSnapshot')->once()->andThrow(new \RuntimeException('PDF_TEST_FAILURE'));
        });
        $this->solicitar($user)->assertStatus(503)->assertJsonPath('code', 'CERTIFICATE_GENERATION_FAILED');
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseCount('solicitudes_certificacion', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('certificados'));
        $this->assertDatabaseHas('audit_logs', ['accion' => 'generacion_certificado_fallida']);
        $this->app->forgetInstance(CertificadoPdfService::class);
        $this->app['router']->getRoutes()->getByName('v1.solicitudes.store')->flushController();
        $this->solicitar($user)->assertCreated();
        $this->assertDatabaseCount('certificados', 1);
    }

    public function test_fallo_bd_despues_de_pdf_elimina_archivo_y_libera_cupo(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200310');
        $this->mock(TokenValidacionService::class, function ($mock) {
            $mock->shouldReceive('crearParaCertificado')->once()->andThrow(new \RuntimeException('DB_TEST_FAILURE'));
        });
        $this->solicitar($user)->assertStatus(503);
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseCount('solicitudes_certificacion', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('certificados'));
        $this->app->forgetInstance(TokenValidacionService::class);
        $this->app['router']->getRoutes()->getByName('v1.solicitudes.store')->flushController();
        $this->solicitar($user)->assertCreated();
    }

    public function test_expedicion_con_funciones_resuelve_ficha_sin_sesiones_administrativas(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/funcionarios', $this->datosFuncionario('100200311'))->assertCreated();
        $user = User::where('documento', '100200311')->firstOrFail();
        $user->update(['must_change_password' => false]);
        $this->assertSame(0, $this->admin->tokens()->count());
        $this->assertSame(0, User::role(RoleEnum::Secretario->value)->count());
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'funciones',
        ])->assertCreated()->assertJsonPath('data.resultado', 'generada');
        $snapshot = Certificado::sole()->snapshot_datos;
        $this->assertNotNull($snapshot['manual_funciones']['relacion_normativa_id']);
        $this->assertSame('planta', $snapshot['asignacion']['tipo_vinculacion']);
        $this->assertSame('2024-01-15', $snapshot['asignacion']['fecha_inicio']);
        $this->assertSame($this->cargo->id, $snapshot['cargo']['id']);
        $this->assertSame('Función sintética de pruebas.', $snapshot['manual_funciones']['funciones_especificas'][0]['descripcion']);
        $this->assertArrayNotHasKey('salario', $snapshot);
        $this->assertDatabaseHas('solicitudes_certificacion', ['estado' => 'generada', 'reviewed_by' => null]);
    }

    public function test_password_pendiente_bloquea_urls_directas_y_sesion_es_minima(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200312', true);
        $this->actingAs($user, 'sanctum');
        foreach (['/api/v1/funcionarios', '/api/v1/configuracion/certificaciones', '/api/v1/mi-certificacion/descargar/inventado'] as $url) {
            $this->getJson($url)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        }
        $this->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo'])
            ->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonMissingPath('data.funcionario')
            ->assertJsonMissingPath('data.password');
    }

    public function test_pago_no_puede_confirmarse_desde_cliente(): void
    {
        ParametroSistema::where('clave', 'requiere_pago_certificado')->update(['valor' => 'true']);
        [$user] = $this->crearFuncionarioDirecto('100200313');
        $response = $this->solicitar($user)->assertCreated();
        $id = $response->json('data.solicitud.id');
        $this->patchJson("/api/v1/solicitudes/{$id}/marcar-pago", ['pagado' => true])->assertStatus(410);
        $this->patchJson('/api/v1/configuracion/certificaciones', ['requiere_pago_certificado' => false])->assertForbidden();
        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_listado_admin_filtra_pagina_y_no_expone_secretos(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/funcionarios', $this->datosFuncionario('100200314'))->assertCreated();
        $this->getJson('/api/v1/funcionarios?q=100200314&per_page=1&estado=activo')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.usuario.must_change_password', true)
            ->assertJsonMissingPath('data.0.usuario.password')
            ->assertJsonStructure(['data' => [['asignacion_actual' => ['ficha_manual']]]]);
    }

    public function test_login_temporal_real_cambio_y_nuevo_login_sin_password_anterior(): void
    {
        $documento = '100200315';
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/funcionarios', $this->datosFuncionario($documento))->assertCreated();
        Auth::forgetGuards();
        $login = $this->postJson('/api/v1/auth/login', ['cedula' => $documento, 'password' => $documento])
            ->assertOk()->assertJsonPath('data.user.must_change_password', true);
        $token = $login->json('data.token');
        Auth::forgetGuards();
        $this->withToken($token)->putJson('/api/v1/auth/change-password', [
            'current_password' => $documento, 'password' => 'OtraClaveNueva2026',
            'password_confirmation' => 'OtraClaveNueva2026',
        ])->assertOk();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/mi-certificacion/disponibilidad')->assertOk();
        $this->flushHeaders();
        Auth::forgetGuards();
        $this->postJson('/api/v1/auth/login', ['cedula' => $documento, 'password' => $documento])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['cedula' => $documento, 'password' => 'OtraClaveNueva2026'])
            ->assertOk()->assertJsonPath('data.user.must_change_password', false);
    }

    public function test_inactivar_revoca_sesiones_y_conserva_certificado(): void
    {
        [$user, $funcionario] = $this->crearFuncionarioDirecto('100200316');
        $this->solicitar($user)->assertCreated();
        $user->createToken('sesion');
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/v1/funcionarios/{$funcionario->id}", ['estado' => 'suspendido'])->assertOk();
        $this->assertFalse($user->fresh()->estado);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseCount('certificados', 1);
        $this->assertDatabaseCount('solicitudes_certificacion', 1);
        $this->solicitar($user->fresh(), 'funciones')->assertForbidden();
    }

    private function solicitar(User $user, string $tipo = 'sencillo')
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => $tipo,
        ]);
    }

    private function crearFuncionarioDirecto(string $documento, bool $cambioPendiente = false): array
    {
        $user = User::factory()->create([
            'documento' => $documento,
            'password' => Hash::make($documento),
            'estado' => true,
            'must_change_password' => $cambioPendiente,
        ]);
        $user->assignRole(RoleEnum::Funcionario->value);
        $funcionario = Funcionario::create([
            'user_id' => $user->id, 'tipo_documento' => 'CC', 'numero_documento' => $documento,
            'nombres' => 'Ana', 'apellidos' => 'Autoservicio', 'estado' => EstadoFuncionarioEnum::Activo,
            'fecha_ingreso' => '2020-01-15', 'dependencia' => 'Talento Humano', 'cargo_id' => $this->cargo->id,
        ]);
        FuncionarioCargo::create([
            'funcionario_id' => $funcionario->id, 'cargo_id' => $this->cargo->id,
            'tipo_vinculacion' => TipoVinculacionEnum::Planta,
            'naturaleza_cargo' => NaturalezaCargoEnum::CarreraAdministrativa,
            'es_cargo_base' => true, 'fecha_inicio' => '2020-01-15',
        ]);

        ManualFixture::vincular($funcionario);

        return [$user, $funcionario];
    }

    private function datosFuncionario(string $documento): array
    {
        return [
            'tipo_documento' => 'CC', 'numero_documento' => $documento,
            'nombres' => 'Laura', 'apellidos' => 'Funcionario',
            'correo_institucional' => "{$documento}@alcaldia.test",
            'estado' => 'activo', 'fecha_ingreso' => '2024-01-15',
            'dependencia' => 'Talento Humano', 'cargo_id' => $this->cargo->id,
            'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa',
        ];
    }
}
