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
use Tests\Support\ManualFixture;
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
        ManualFixture::vincular($this->funcionario);
    }

    public function test_funcionario_puede_crear_solicitud(): void
    {
        $response = $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
                'requiere_salario' => false,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resultado', 'generada')
            ->assertJsonPath('data.solicitud.estado', EstadoSolicitudEnum::Generada->value)
            ->assertJsonPath('data.solicitud.tipo_certificado', TipoCertificadoEnum::Laboral->value)
            ->assertJsonPath('data.solicitud.requiere_pago', false);

        $this->assertDatabaseHas('audit_logs', ['accion' => 'solicitar_certificacion_autoservicio']);
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

    public function test_flujo_manual_de_rechazo_esta_retirado(): void
    {
        $solicitud = $this->crearSolicitud();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/rechazar")
            ->assertStatus(410)
            ->assertJsonPath('code', 'LEGACY_MANUAL_APPROVAL_DISABLED');
    }

    public function test_secretario_no_puede_aprobar_en_el_flujo_automatico(): void
    {
        $solicitud = $this->crearSolicitud(['estado' => EstadoSolicitudEnum::EnRevision]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/aprobar")
            ->assertStatus(410)
            ->assertJsonPath('code', 'LEGACY_MANUAL_APPROVAL_DISABLED');

        $this->assertDatabaseMissing('audit_logs', ['accion' => 'aprobar_solicitud']);
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
                'requiere_salario' => false,
            ]);

        $response->assertCreated();
        $this->assertMatchesRegularExpression('/^CL-\d{4}-\d{6}$/', $response->json('data.solicitud.radicado'));
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
