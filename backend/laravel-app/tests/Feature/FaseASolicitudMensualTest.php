<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoVinculacionEnum;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ManualFixture;
use Tests\TestCase;

class FaseASolicitudMensualTest extends TestCase
{
    use RefreshDatabase;

    private User $usuarioA;

    private User $usuarioB;

    private Funcionario $funcionarioA;

    private Funcionario $funcionarioB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-05 10:00:00', 'America/Bogota'));

        $cargo = Cargo::create([
            'codigo' => '219', 'grado' => '02',
            'denominacion' => 'Profesional Universitario', 'estado' => true,
        ]);
        [$this->usuarioA, $this->funcionarioA] = $this->crearFuncionario('10000001', $cargo);
        [$this->usuarioB, $this->funcionarioB] = $this->crearFuncionario('10000002', $cargo);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_un_sencillo_y_un_funciones_por_mes_y_renovacion_en_mes_siguiente(): void
    {
        $this->radicar($this->usuarioA, 'sencillo')->assertCreated();
        $this->radicar($this->usuarioA, 'sencillo')->assertConflict()
            ->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');
        $this->radicar($this->usuarioA, 'funciones')->assertCreated();
        $this->radicar($this->usuarioA, 'funciones')->assertConflict()
            ->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');

        $this->radicar($this->usuarioB, 'sencillo')->assertCreated();
        $this->radicar($this->usuarioB, 'funciones')->assertCreated();

        $this->assertDatabaseHas('solicitudes_certificacion', [
            'funcionario_id' => $this->funcionarioA->id,
            'periodo_mes' => '2026-08-01',
            'tipo_certificado' => 'sencillo',
        ]);
        $this->assertDatabaseCount('solicitudes_certificacion', 4);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 00:01:00', 'America/Bogota'));
        $this->radicar($this->usuarioA, 'sencillo')->assertCreated();
        $this->radicar($this->usuarioA, 'funciones')->assertCreated();
    }

    public function test_solicitud_rechazada_conserva_cupo_del_mismo_tipo(): void
    {
        $this->radicar($this->usuarioA, 'sencillo')->assertCreated();
        SolicitudCertificacion::firstOrFail()->update(['estado' => EstadoSolicitudEnum::Rechazada]);

        $this->radicar($this->usuarioA, 'sencillo')->assertConflict();
        $this->radicar($this->usuarioA, 'funciones')->assertCreated();
    }

    public function test_tipo_canonico_es_obligatorio_y_payload_salarial_es_rechazado(): void
    {
        $this->actingAs($this->usuarioA, 'sanctum')->postJson('/api/v1/solicitudes', [])
            ->assertUnprocessable()->assertJsonValidationErrors('tipo_certificado');

        $this->actingAs($this->usuarioA, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'laboral',
        ])->assertUnprocessable()->assertJsonValidationErrors('tipo_certificado');

        $this->actingAs($this->usuarioA, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'sencillo',
            'requiere_salario' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('requiere_salario');

        $this->actingAs($this->usuarioA, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo' => 'sencillo',
        ])->assertUnprocessable()->assertJsonValidationErrors(['tipo', 'tipo_certificado']);
    }

    public function test_funcionario_id_enviado_por_cliente_es_rechazado(): void
    {
        $this->actingAs($this->usuarioA, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'sencillo',
            'funcionario_id' => $this->funcionarioB->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('funcionario_id');
    }

    public function test_disponibilidad_expone_sencillo_y_funciones(): void
    {
        $this->actingAs($this->usuarioA, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertOk()
            ->assertJsonPath('data.periodo', '2026-08-01')
            ->assertJsonPath('data.sencillo.puede_solicitar', true)
            ->assertJsonPath('data.funciones.puede_solicitar', true)
            ->assertJsonMissingPath('data.con_salario')
            ->assertJsonMissingPath('data.sin_salario');

        $this->radicar($this->usuarioA, 'sencillo')->assertCreated();
        $this->actingAs($this->usuarioA, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertJsonPath('data.sencillo.puede_solicitar', false)
            ->assertJsonPath('data.sencillo.proxima_fecha_disponible', '2026-09-01')
            ->assertJsonPath('data.funciones.puede_solicitar', true);
    }

    public function test_constraint_postgresql_impide_colision_del_mismo_tipo(): void
    {
        $this->radicar($this->usuarioA, 'sencillo')->assertCreated();
        $this->radicar($this->usuarioA, 'sencillo')->assertConflict();

        $this->expectException(QueryException::class);
        SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionarioA->id,
            'tipo_certificado' => 'sencillo',
            'estado' => EstadoSolicitudEnum::Pendiente,
            'periodo_mes' => '2026-08-01',
            'created_by' => $this->usuarioA->id,
        ]);
    }

    public function test_rutas_salariales_fueron_retiradas(): void
    {
        $admin = User::factory()->create(['estado' => true]);
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/rangos-salariales')->assertNotFound();
        $this->assertFalse($admin->can('rangos_salariales.ver'));
    }

    private function radicar(User $usuario, string $tipo)
    {
        return $this->actingAs($usuario, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => $tipo,
        ]);
    }

    private function crearFuncionario(string $documento, Cargo $cargo): array
    {
        $usuario = User::factory()->create(['documento' => $documento, 'estado' => true]);
        $usuario->assignRole(RoleEnum::Funcionario->value);
        $funcionario = Funcionario::create([
            'user_id' => $usuario->id,
            'tipo_documento' => 'CC',
            'numero_documento' => $documento,
            'nombres' => 'Funcionario',
            'apellidos' => $documento,
            'estado' => EstadoFuncionarioEnum::Activo,
            'cargo_id' => $cargo->id,
        ]);
        FuncionarioCargo::create([
            'funcionario_id' => $funcionario->id,
            'cargo_id' => $cargo->id,
            'tipo_vinculacion' => TipoVinculacionEnum::Planta,
            'naturaleza_cargo' => NaturalezaCargoEnum::CarreraAdministrativa,
            'es_cargo_base' => true,
            'fecha_inicio' => '2020-01-15',
        ]);
        ManualFixture::vincular($funcionario);

        return [$usuario, $funcionario];
    }
}
