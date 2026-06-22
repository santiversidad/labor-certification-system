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
            'codigo'      => '219',
            'grado'       => '02',
            'denominacion' => 'Profesional Universitario',
            'estado'      => true,
        ]);

        $this->userFuncionario = User::factory()->create(['estado' => true]);
        $this->userFuncionario->assignRole(RoleEnum::Funcionario->value);

        $this->funcionario = Funcionario::create([
            'user_id'          => $this->userFuncionario->id,
            'tipo_documento'   => 'CC',
            'numero_documento' => '987654321',
            'nombres'          => 'Juan',
            'apellidos'        => 'Pérez',
            'estado'           => EstadoFuncionarioEnum::Activo,
            'cargo_id'         => $cargo->id,
        ]);
    }

    // ─── Creación ─────────────────────────────────────────────────────────────

    public function test_funcionario_puede_crear_solicitud(): void
    {
        $response = $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::Pendiente->value)
            ->assertJsonPath('data.tipo_certificado', TipoCertificadoEnum::Laboral->value);

        $this->assertDatabaseHas('solicitudes_certificacion', [
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente->value,
        ]);
    }

    public function test_admin_no_puede_crear_solicitud(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            ])
            ->assertForbidden();
    }

    public function test_crear_solicitud_falla_sin_tipo_certificado(): void
    {
        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_certificado']);
    }

    public function test_crear_solicitud_falla_si_usuario_no_tiene_funcionario(): void
    {
        $userSinFuncionario = User::factory()->create(['estado' => true]);
        $userSinFuncionario->assignRole(RoleEnum::Funcionario->value);

        $this->actingAs($userSinFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_funcionario_no_puede_crear_segunda_solicitud_activa(): void
    {
        // Primera solicitud en estado activo
        SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
            'created_by'       => $this->userFuncionario->id,
        ]);

        // Intento de crear segunda solicitud
        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Funciones->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_funcionario_puede_crear_solicitud_si_anterior_fue_rechazada(): void
    {
        // Solicitud rechazada (estado terminal, no bloquea)
        SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Rechazado,
            'created_by'       => $this->userFuncionario->id,
        ]);

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_solicitud_creada_tiene_radicado(): void
    {
        $response = $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson('/api/v1/solicitudes', [
                'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            ]);

        $response->assertCreated();
        $radicado = $response->json('data.radicado');
        $this->assertNotNull($radicado);
        $this->assertMatchesRegularExpression('/^CL-\d{4}-\d{6}$/', $radicado);
    }

    public function test_secretario_puede_aprobar_via_alias(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::EnRevision,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/aprobar")
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::Aprobado->value);
    }

    public function test_secretario_puede_rechazar_via_alias(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/rechazar", [
                'motivo_rechazo' => 'Documentación incompleta presentada por el funcionario.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::Rechazado->value);
    }

    public function test_rechazar_via_alias_sin_motivo_falla(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/rechazar")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_rechazo']);
    }

    // ─── Listado ──────────────────────────────────────────────────────────────

    public function test_admin_ve_todas_las_solicitudes(): void
    {
        SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_funcionario_solo_ve_sus_propias_solicitudes(): void
    {
        // Solicitud del funcionario de prueba
        SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        // Otro funcionario con otra solicitud
        $otroFuncionario = Funcionario::create([
            'tipo_documento'   => 'CC',
            'numero_documento' => '111222333',
            'nombres'          => 'Otro',
            'apellidos'        => 'Funcionario',
            'estado'           => EstadoFuncionarioEnum::Activo,
        ]);
        SolicitudCertificacion::create([
            'funcionario_id'   => $otroFuncionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Salario->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        // El funcionario de prueba solo debe ver la suya (total: 1)
        $this->actingAs($this->userFuncionario, 'sanctum')
            ->getJson('/api/v1/solicitudes')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ─── Detalle ─────────────────────────────────────────────────────────────

    public function test_funcionario_puede_ver_su_propia_solicitud(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->getJson("/api/v1/solicitudes/{$solicitud->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $solicitud->id);
    }

    public function test_funcionario_no_puede_ver_solicitud_ajena(): void
    {
        $otroFuncionario = Funcionario::create([
            'tipo_documento'   => 'CC',
            'numero_documento' => '111222333',
            'nombres'          => 'Otro',
            'apellidos'        => 'Funcionario',
            'estado'           => EstadoFuncionarioEnum::Activo,
        ]);
        $solicitudAjena = SolicitudCertificacion::create([
            'funcionario_id'   => $otroFuncionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->getJson("/api/v1/solicitudes/{$solicitudAjena->id}")
            ->assertForbidden();
    }

    // ─── Cambio de estado ─────────────────────────────────────────────────────

    public function test_secretario_puede_poner_solicitud_en_revision(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
                'estado' => EstadoSolicitudEnum::EnRevision->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::EnRevision->value);
    }

    public function test_secretario_puede_rechazar_solicitud_con_motivo(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
                'estado'         => EstadoSolicitudEnum::Rechazado->value,
                'motivo_rechazo' => 'Documentación incompleta.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoSolicitudEnum::Rechazado->value);
    }

    public function test_rechazar_sin_motivo_falla(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
                'estado' => EstadoSolicitudEnum::Rechazado->value,
                // sin motivo_rechazo
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo_rechazo']);
    }

    public function test_transicion_invalida_es_rechazada(): void
    {
        // No se puede pasar de 'pendiente' directamente a 'generado'
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
                'estado' => EstadoSolicitudEnum::Generado->value,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_funcionario_no_puede_cambiar_estado(): void
    {
        $solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral->value,
            'estado'           => EstadoSolicitudEnum::Pendiente,
        ]);

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->patchJson("/api/v1/solicitudes/{$solicitud->id}/estado", [
                'estado' => EstadoSolicitudEnum::EnRevision->value,
            ])
            ->assertForbidden();
    }

    public function test_solicitud_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/solicitudes')
            ->assertUnauthorized();
    }
}
