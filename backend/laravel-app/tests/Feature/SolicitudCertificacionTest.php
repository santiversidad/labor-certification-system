<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoCertificadoEnum;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudCertificacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretario;
    private User $userFuncionario;
    private Funcionario $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);

        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);

        $this->secretario = User::factory()->create(['estado' => true]);
        $this->secretario->assignRole(RoleEnum::Secretario->value);

        $cargo = Cargo::create([
            'codigo' => '219',
            'grado' => '02',
            'denominacion' => 'Profesional Universitario',
            'estado' => true,
        ]);

        $this->userFuncionario = User::factory()->create(['estado' => true]);
        $this->userFuncionario->assignRole(RoleEnum::Funcionario->value);

        $this->funcionario = Funcionario::create([
            'user_id' => $this->userFuncionario->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '987654321',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'estado' => EstadoFuncionarioEnum::Activo,
            'cargo_id' => $cargo->id,
        ]);
    }

    public function test_funcionario_puede_crear_solicitud(): void
    {
        $response = $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
                'requiere_salario' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::Pendiente->value)
            ->assertJsonPath('data.tipo_certificado', TipoCertificadoEnum::Laboral->value)
            ->assertJsonPath('data.requiere_pago', false);

        $this->assertDatabaseHas('audit_logs', ['accion' => 'crear_solicitud']);
    }

    public function test_funcionario_no_puede_listar_solicitudes(): void
    {
        $this->actingAs($this->userFuncionario, 'sanctum')
            ->getJson('/api/v1/solicitudes')
            ->assertForbidden();
    }

    public function test_secretario_y_admin_pueden_listar_solicitudes(): void
    {
        $this->crearSolicitud();

        $this->actingAs($this->secretario, 'sanctum')
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_rechazar_solicitud_exige_observacion(): void
    {
        $solicitud = $this->crearSolicitud();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/rechazar")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_rechazo']);
    }

    public function test_secretario_puede_aprobar_y_registra_auditoria(): void
    {
        $solicitud = $this->crearSolicitud(['estado' => EstadoSolicitudEnum::EnRevision]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::Aprobada->value);

        $this->assertDatabaseHas('audit_logs', ['accion' => 'aprobar_solicitud']);
    }

    public function test_no_se_puede_generar_certificado_de_solicitud_rechazada(): void
    {
        $solicitud = $this->crearSolicitud(['estado' => EstadoSolicitudEnum::Rechazada]);

        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertStatus(422);
    }

    public function test_solicitud_creada_tiene_radicado(): void
    {
        $response = $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            ]);

        $response->assertCreated();
        $this->assertMatchesRegularExpression('/^CL-\d{4}-\d{6}$/', $response->json('data.radicado'));
    }

    private function crearSolicitud(array $overrides = []): SolicitudCertificacion
    {
        return SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
            'estado' => EstadoSolicitudEnum::Pendiente,
            'created_by' => $this->userFuncionario->id,
            ...$overrides,
        ]);
    }
}
