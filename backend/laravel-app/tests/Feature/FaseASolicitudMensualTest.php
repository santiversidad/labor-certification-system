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
use App\Models\RangoSalarial;
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
        RangoSalarial::create([
            'codigo' => '219', 'grado' => '02', 'vigencia_anio' => 2026,
            'salario_basico' => 5000000, 'moneda' => 'COP', 'estado' => true,
        ]);
        [$this->usuarioA, $this->funcionarioA] = $this->crearFuncionario('10000001', $cargo);
        [$this->usuarioB, $this->funcionarioB] = $this->crearFuncionario('10000002', $cargo);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_una_con_y_una_sin_salario_por_mes_y_renovacion_en_mes_siguiente(): void
    {
        $this->radicar($this->usuarioA, false)->assertCreated();
        $this->radicar($this->usuarioA, false)->assertStatus(409)
            ->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');
        $this->radicar($this->usuarioA, true)->assertCreated();
        $this->radicar($this->usuarioA, true)->assertStatus(409)
            ->assertJsonPath('code', 'MONTHLY_CERTIFICATE_LIMIT');

        $this->radicar($this->usuarioB, false)->assertCreated();
        $this->radicar($this->usuarioB, true)->assertCreated();

        $this->assertDatabaseHas('solicitudes_certificacion', [
            'funcionario_id' => $this->funcionarioA->id,
            'periodo_mes' => '2026-08-01',
            'requiere_salario' => false,
        ]);
        $this->assertDatabaseCount('solicitudes_certificacion', 4);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 00:01:00', 'America/Bogota'));
        $this->radicar($this->usuarioA, false)->assertCreated();
        $this->radicar($this->usuarioA, true)->assertCreated();
        $this->assertDatabaseHas('solicitudes_certificacion', [
            'funcionario_id' => $this->funcionarioA->id,
            'periodo_mes' => '2026-09-01',
            'requiere_salario' => true,
        ]);
    }

    public function test_solicitud_rechazada_conserva_cupo_de_su_modalidad(): void
    {
        $this->radicar($this->usuarioA, false)->assertCreated();
        SolicitudCertificacion::firstOrFail()->update(['estado' => EstadoSolicitudEnum::Rechazada]);

        $this->radicar($this->usuarioA, false)->assertStatus(409);
        $this->radicar($this->usuarioA, true)->assertCreated();
    }

    public function test_modalidad_es_obligatoria_y_se_persiste_sin_ambiguedad(): void
    {
        $this->radicar($this->usuarioA, true)->assertCreated()
            ->assertJsonPath('data.solicitud.requiere_salario', true);

        $this->actingAs($this->usuarioB, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'laboral',
        ])->assertStatus(422)->assertJsonValidationErrors('requiere_salario');

        $this->actingAs($this->usuarioB, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'laboral',
            'requiere_salario' => ['valor-invalido'],
        ])->assertStatus(422)->assertJsonValidationErrors('requiere_salario');

        $this->radicar($this->usuarioB, false)->assertCreated()
            ->assertJsonPath('data.solicitud.requiere_salario', false);
    }

    public function test_funcionario_id_enviado_por_cliente_es_rechazado_y_no_hay_idor(): void
    {
        $this->actingAs($this->usuarioA, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'laboral',
            'requiere_salario' => false,
            'funcionario_id' => $this->funcionarioB->id,
        ])->assertStatus(422)->assertJsonValidationErrors('funcionario_id');

        $this->assertDatabaseMissing('solicitudes_certificacion', [
            'funcionario_id' => $this->funcionarioB->id,
        ]);
    }

    public function test_disponibilidad_solo_expone_cupos_de_ambas_modalidades(): void
    {
        $this->actingAs($this->usuarioA, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertOk()
            ->assertJsonPath('data.periodo', '2026-08-01')
            ->assertJsonPath('data.con_salario.puede_solicitar', true)
            ->assertJsonPath('data.sin_salario.puede_solicitar', true)
            ->assertJsonMissingPath('data.funcionario_id')
            ->assertJsonMissingPath('data.solicitudes');

        $this->radicar($this->usuarioA, false)->assertCreated();
        $this->actingAs($this->usuarioA, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertJsonPath('data.con_salario.puede_solicitar', true)
            ->assertJsonPath('data.sin_salario.puede_solicitar', false)
            ->assertJsonPath('data.sin_salario.proxima_fecha_disponible', '2026-09-01');

        $this->radicar($this->usuarioA, true)->assertCreated();
        $this->actingAs($this->usuarioA, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertJsonPath('data.con_salario.puede_solicitar', false)
            ->assertJsonPath('data.sin_salario.puede_solicitar', false);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01', 'America/Bogota'));
        $this->actingAs($this->usuarioA, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertJsonPath('data.con_salario.puede_solicitar', true)
            ->assertJsonPath('data.sin_salario.puede_solicitar', true);
    }

    public function test_constraint_postgresql_impide_colision_y_api_la_convierte_en_conflicto(): void
    {
        $this->radicar($this->usuarioA, false)->assertCreated();
        $this->radicar($this->usuarioA, false)->assertStatus(409);
        $this->assertDatabaseCount('solicitudes_certificacion', 1);

        $this->expectException(QueryException::class);
        SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionarioA->id,
            'tipo_certificado' => 'laboral',
            'estado' => EstadoSolicitudEnum::Pendiente,
            'requiere_salario' => false,
            'periodo_mes' => '2026-08-01',
            'created_by' => $this->usuarioA->id,
        ]);
    }

    public function test_funcionario_no_accede_a_endpoints_internos(): void
    {
        $rutas = [
            '/api/v1/funcionarios', '/api/v1/funcionarios/'.$this->funcionarioB->id,
            '/api/v1/cargos', '/api/v1/rangos-salariales',
            '/api/v1/actuaciones-administrativas', '/api/v1/reportes', '/api/v1/auditoria',
            '/api/v1/solicitudes',
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($this->usuarioA, 'sanctum')->getJson($ruta)->assertForbidden();
        }
    }

    private function radicar(User $usuario, bool $requiereSalario)
    {
        return $this->actingAs($usuario, 'sanctum')->postJson('/api/v1/solicitudes', [
            'tipo_certificado' => 'laboral',
            'requiere_salario' => $requiereSalario,
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
