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
use App\Models\ParametroSistema;
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

    private User $secretario;
    private User $userFuncionario;
    private Funcionario $funcionario;
    private SolicitudCertificacion $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

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

        $this->solicitud = SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
            'estado' => EstadoSolicitudEnum::PendientePago,
            'requiere_pago' => true,
            'created_by' => $this->userFuncionario->id,
        ]);
    }

    public function test_pago_no_obligatorio_si_configuracion_esta_apagada(): void
    {
        $archivo = UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", ['archivo' => $archivo])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_soporte_solo_acepta_pdf_jpg_png(): void
    {
        $this->activarPagos();
        $archivo = UploadedFile::fake()->create('soporte.exe', 100, 'application/octet-stream');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", ['archivo' => $archivo])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_funcionario_puede_cargar_soporte_propio(): void
    {
        $this->activarPagos();
        $archivo = UploadedFile::fake()->create('soporte.pdf', 500, 'application/pdf');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", ['archivo' => $archivo])
            ->assertCreated()
            ->assertJsonPath('data.estado', EstadoPagoEnum::Cargado->value);

        $this->assertDatabaseHas('solicitudes_certificacion', [
            'id' => $this->solicitud->id,
            'estado' => EstadoSolicitudEnum::PagoEnRevision->value,
        ]);
    }

    public function test_secretario_puede_aprobar_pago(): void
    {
        $pago = $this->crearPagoCargado();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/validar")
            ->assertOk()
            ->assertJsonPath('data.estado', EstadoPagoEnum::Aprobado->value);

        $this->assertDatabaseHas('solicitudes_certificacion', [
            'id' => $this->solicitud->id,
            'estado' => EstadoSolicitudEnum::Aprobada->value,
        ]);
    }

    public function test_rechazar_pago_exige_observacion(): void
    {
        $pago = $this->crearPagoCargado();

        $this->actingAs($this->secretario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/rechazar")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_funcionario_no_puede_validar_pago(): void
    {
        $pago = $this->crearPagoCargado();

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->patchJson("/api/v1/pagos/{$pago->id}/validar")
            ->assertForbidden();
    }

    private function activarPagos(): void
    {
        ParametroSistema::updateOrCreate(
            ['clave' => 'requiere_pago_certificado'],
            ['valor' => 'true', 'tipo' => 'boolean'],
        );
    }

    private function crearPagoCargado(): PagoSoporte
    {
        $this->solicitud->update(['estado' => EstadoSolicitudEnum::PagoEnRevision]);

        return PagoSoporte::create([
            'solicitud_certificacion_id' => $this->solicitud->id,
            'funcionario_id' => $this->funcionario->id,
            'archivo_path' => 'pagos-soportes/1/soporte.pdf',
            'archivo_original_nombre' => 'soporte.pdf',
            'estado' => EstadoPagoEnum::Cargado,
        ]);
    }
}
