<?php

namespace Tests\Feature;

use App\Enums\EstadoCertificadoEnum;
use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoCertificadoEnum;
use App\Models\Cargo;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificadoValidacionTest extends TestCase
{
    use RefreshDatabase;

    private User $secretario;
    private User $admin;
    private User $funcionarioUser;
    private Funcionario $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->secretario = User::factory()->create(['estado' => true]);
        $this->secretario->assignRole(RoleEnum::Secretario->value);

        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);

        $this->funcionarioUser = User::factory()->create(['estado' => true]);
        $this->funcionarioUser->assignRole(RoleEnum::Funcionario->value);

        $cargo = Cargo::create([
            'codigo' => '219',
            'grado' => '02',
            'denominacion' => 'Profesional Universitario',
            'estado' => true,
        ]);

        $this->funcionario = Funcionario::create([
            'user_id' => $this->funcionarioUser->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '987654321',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'estado' => EstadoFuncionarioEnum::Activo,
            'cargo_id' => $cargo->id,
        ]);
    }

    public function test_secretario_puede_generar_certificado_de_solicitud_aprobada(): void
    {
        $solicitud = $this->crearSolicitudAprobada();

        $response = $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado");

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['certificado', 'token', 'url_validacion']]);

        $this->assertDatabaseCount('certificados', 1);
        $this->assertDatabaseCount('tokens_validacion', 1);
        $this->assertDatabaseHas('solicitudes_certificacion', [
            'id' => $solicitud->id,
            'estado' => EstadoSolicitudEnum::CertificadoGenerado->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'generar_certificado']);
    }

    public function test_no_genera_certificado_si_pago_requerido_no_esta_aprobado(): void
    {
        $solicitud = $this->crearSolicitudAprobada(['requiere_pago' => true]);

        PagoSoporte::create([
            'solicitud_certificacion_id' => $solicitud->id,
            'funcionario_id' => $this->funcionario->id,
            'archivo_path' => 'pagos-soportes/1/soporte.pdf',
            'archivo_original_nombre' => 'soporte.pdf',
            'estado' => EstadoPagoEnum::Cargado,
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertStatus(422);
    }

    public function test_token_valido_retorna_certificado_valido_y_no_expone_salario(): void
    {
        $token = $this->generarCertificadoYToken();

        $response = $this->getJson("/api/v1/validar-certificado/{$token}");

        $response->assertOk()
            ->assertJsonPath('data.valido', true)
            ->assertJsonMissingPath('data.salario');
    }

    public function test_token_invalido_retorna_respuesta_controlada(): void
    {
        $this->getJson('/api/v1/validar-certificado/token-inexistente')
            ->assertStatus(404)
            ->assertJsonPath('data.valido', false);
    }

    public function test_certificado_anulado_no_valida_como_vigente(): void
    {
        $token = $this->generarCertificadoYToken();
        $certificado = Certificado::firstOrFail();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/certificados/{$certificado->id}/anular", ['motivo' => 'Error en datos'])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoCertificadoEnum::Anulado->value);

        $this->getJson("/api/v1/validar-certificado/{$token}")
            ->assertOk()
            ->assertJsonPath('data.valido', false);
    }

    public function test_descarga_requiere_autorizacion(): void
    {
        $this->generarCertificadoYToken();
        $certificado = Certificado::firstOrFail();

        $this->getJson("/api/v1/certificados/{$certificado->id}/descargar")
            ->assertUnauthorized();

        $this->actingAs($this->secretario, 'sanctum')
            ->get("/api/v1/certificados/{$certificado->id}/descargar")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['accion' => 'descargar_certificado']);
    }

    private function generarCertificadoYToken(): string
    {
        $solicitud = $this->crearSolicitudAprobada();

        $response = $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        return $response->json('data.token');
    }

    private function crearSolicitudAprobada(array $overrides = []): SolicitudCertificacion
    {
        return SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
            'estado' => EstadoSolicitudEnum::Aprobada,
            'created_by' => $this->funcionarioUser->id,
            ...$overrides,
        ]);
    }
}
