<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoVinculacionEnum;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ParametroSistema;
use App\Models\RangoSalarial;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
        RangoSalarial::create([
            'codigo' => '219', 'grado' => '02', 'vigencia_anio' => 2026,
            'salario_basico' => 5000000, 'moneda' => 'COP', 'estado' => true,
        ]);
        ParametroSistema::create([
            'clave' => 'requiere_pago_certificado', 'valor' => 'false', 'tipo' => 'boolean',
        ]);
        \Tests\Support\ManualFixture::ficha($this->cargo);
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
            'tipo_certificado' => 'laboral', 'requiere_salario' => false,
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

    public function test_cupos_son_independientes_y_segunda_modalidad_igual_se_bloquea(): void
    {
        [$user] = $this->crearFuncionarioDirecto('100200305');
        $this->solicitar($user, false)->assertCreated();
        $this->solicitar($user, false)->assertConflict()->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');
        $this->solicitar($user, true)->assertCreated()->assertJsonPath('data.resultado', 'generada');
        $this->solicitar($user, true)->assertConflict();
        $this->assertDatabaseCount('certificados', 2);
    }

    public function test_pago_activado_crea_orden_pendiente_y_no_genera_certificado(): void
    {
        ParametroSistema::where('clave', 'requiere_pago_certificado')->update(['valor' => 'true']);
        [$user] = $this->crearFuncionarioDirecto('100200306');

        $this->solicitar($user, false)->assertCreated()
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
        $this->solicitar($user, false)->assertForbidden()->assertJsonPath('code', 'INACTIVE_EMPLOYEE');

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

    private function solicitar(User $user, bool $conSalario)
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'laboral', 'requiere_salario' => $conSalario,
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

        \Tests\Support\ManualFixture::vincular($funcionario);
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
