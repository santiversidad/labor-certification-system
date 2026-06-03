<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoCertificadoEnum;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagoSoporteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretario;
    private User $userFuncionario;
    private Funcionario $funcionario;
    private SolicitudCertificacion $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);

        // Usar disco falso para tests — no escribe en disco real
        Storage::fake('local');

        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);

        $this->secretario = User::factory()->create(['estado' => true]);
        $this->secretario->assignRole(RoleEnum::Secretario->value);

        $cargo = Cargo::create([
            'codigo'       => '219',
            'grado'        => '02',
            'denominacion' => 'Profesional Universitario',
            'estado'       => true,
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

        // Solicitud base en estado 'requiere_pago'
        $this->solicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
            'estado'           => EstadoSolicitudEnum::RequierePago,
            'requiere_pago'    => true,
            'created_by'       => $this->userFuncionario->id,
        ]);
    }

    // ─── Carga de soporte ─────────────────────────────────────────────────────

    public function test_funcionario_puede_cargar_soporte_pdf(): void
    {
        $archivo = UploadedFile::fake()->create('soporte.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", [
                'archivo' => $archivo,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', EstadoPagoEnum::Pendiente->value)
            ->assertJsonPath('data.archivo_original_nombre', 'soporte.pdf');

        // El archivo debe estar en el disco fake
        Storage::disk('local')->assertExists("pagos-soportes/{$this->solicitud->id}");

        // La solicitud debe haber avanzado a pago_pendiente
        $this->assertDatabaseHas('solicitudes_certificacion', [
            'id'     => $this->solicitud->id,
            'estado' => EstadoSolicitudEnum::PagoPendiente->value,
        ]);
    }

    public function test_funcionario_puede_cargar_soporte_imagen(): void
    {
        $archivo = UploadedFile::fake()->image('recibo.jpg');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", [
                'archivo' => $archivo,
            ])
            ->assertCreated();
    }

    public function test_cargar_soporte_falla_sin_archivo(): void
    {
        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_cargar_soporte_falla_con_tipo_no_permitido(): void
    {
        $archivo = UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", [
                'archivo' => $archivo,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_no_se_puede_cargar_soporte_si_solicitud_no_requiere_pago(): void
    {
        $otraSolicitud = SolicitudCertificacion::create([
            'funcionario_id'   => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
            'estado'           => EstadoSolicitudEnum::Pendiente, // No está en requiere_pago
        ]);

        $archivo = UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$otraSolicitud->id}/soporte-pago", [
                'archivo' => $archivo,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_funcionario_ajeno_no_puede_cargar_soporte(): void
    {
        $otroUser = User::factory()->create(['estado' => true]);
        $otroUser->assignRole(RoleEnum::Funcionario->value);
        Funcionario::create([
            'user_id'          => $otroUser->id,
            'tipo_documento'   => 'CC',
            'numero_documento' => '111222333',
            'nombres'          => 'Otro',
            'apellidos'        => 'Funcionario',
            'estado'           => EstadoFuncionarioEnum::Activo,
        ]);

        $archivo = UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf');

        $this->actingAs($otroUser, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", [
                'archivo' => $archivo,
            ])
            ->assertForbidden();
    }

    // ─── Validación de soporte ────────────────────────────────────────────────

    private function crearPagoPendiente(): PagoSoporte
    {
        $pago = PagoSoporte::create([
            'solicitud_certificacion_id' => $this->solicitud->id,
            'funcionario_id'             => $this->funcionario->id,
            'archivo_path'               => 'pagos-soportes/1/soporte.pdf',
            'archivo_original_nombre'    => 'soporte.pdf',
            'estado'                     => EstadoPagoEnum::Pendiente,
        ]);

        $this->solicitud->update(['estado' => EstadoSolicitudEnum::PagoPendiente]);

        return $pago;
    }

    public function test_secretario_puede_validar_pago_pendiente(): void
    {
        $pago = $this->crearPagoPendiente();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/validar", [
                'observaciones' => 'Pago verificado correctamente.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoPagoEnum::Aprobado->value);

        $this->assertDatabaseHas('solicitudes_certificacion', [
            'id'     => $this->solicitud->id,
            'estado' => EstadoSolicitudEnum::PagoValidado->value,
        ]);
    }

    public function test_admin_puede_validar_pago(): void
    {
        $pago = $this->crearPagoPendiente();

        $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/validar", [])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoPagoEnum::Aprobado->value);
    }

    public function test_secretario_puede_rechazar_pago_con_observacion(): void
    {
        $pago = $this->crearPagoPendiente();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/rechazar", [
                'observaciones' => 'El soporte está ilegible.',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoPagoEnum::Rechazado->value);

        // La solicitud regresa a requiere_pago
        $this->assertDatabaseHas('solicitudes_certificacion', [
            'id'     => $this->solicitud->id,
            'estado' => EstadoSolicitudEnum::RequierePago->value,
        ]);
    }

    public function test_rechazar_pago_sin_observacion_falla(): void
    {
        $pago = $this->crearPagoPendiente();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/rechazar", [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_no_se_puede_validar_un_pago_ya_aprobado(): void
    {
        $pago = PagoSoporte::create([
            'solicitud_certificacion_id' => $this->solicitud->id,
            'funcionario_id'             => $this->funcionario->id,
            'archivo_path'               => 'pagos-soportes/1/soporte.pdf',
            'archivo_original_nombre'    => 'soporte.pdf',
            'estado'                     => EstadoPagoEnum::Aprobado, // ya aprobado
        ]);

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/validar", [])
            ->assertStatus(422);
    }

    public function test_funcionario_no_puede_validar_pagos(): void
    {
        $pago = $this->crearPagoPendiente();

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/validar", [])
            ->assertForbidden();
    }

    public function test_endpoints_de_pagos_requieren_autenticacion(): void
    {
        $this->patchJson('/api/v1/pagos/1/validar', [])->assertUnauthorized();
        $this->patchJson('/api/v1/pagos/1/rechazar', [])->assertUnauthorized();
        $this->getJson('/api/v1/pagos')->assertUnauthorized();
    }
}
