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
use App\Services\PagoSoporteService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagoSoporteTest extends TestCase
{
    use RefreshDatabase;

    private User $secretario;

    private User $admin;

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
        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);

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
            'tipo_certificado' => TipoCertificadoEnum::Sencillo,
            'estado' => EstadoSolicitudEnum::PendientePago,
            'requiere_pago' => true,
            'created_by' => $this->userFuncionario->id,
        ]);
    }

    public function test_funcionario_no_puede_cargar_soporte_manual_con_pago_apagado(): void
    {
        $archivo = UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", ['archivo' => $archivo])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_funcionario_no_puede_usar_ruta_legacy_de_soporte(): void
    {
        $this->activarPagos();
        $archivo = UploadedFile::fake()->create('soporte.exe', 100, 'application/octet-stream');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", ['archivo' => $archivo])
            ->assertForbidden();
    }

    public function test_funcionario_no_carga_comprobante_manual_aunque_pago_este_activo(): void
    {
        $this->activarPagos();
        $archivo = UploadedFile::fake()->create('soporte.pdf', 500, 'application/pdf');

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$this->solicitud->id}/soporte-pago", ['archivo' => $archivo])
            ->assertForbidden();

        $this->assertDatabaseMissing('pagos_soportes', ['solicitud_certificacion_id' => $this->solicitud->id]);
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

    public function test_admin_y_secretario_pueden_descargar_soporte_privado_con_headers_seguros(): void
    {
        $pago = $this->crearPagoCargado();
        Storage::disk('local')->put($pago->archivo_path, '%PDF-soporte-privado');

        foreach ([$this->admin, $this->secretario] as $gestor) {
            $response = $this->actingAs($gestor, 'sanctum')
                ->get("/api/v1/pagos/{$pago->id}/archivo");

            $response->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('x-content-type-options', 'nosniff');
            $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
            $this->assertStringNotContainsString($pago->archivo_path, (string) $response->getContent());
        }

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'visualizar_soporte_pago']);
    }

    public function test_soporte_privado_rechaza_funcionario_anonimo_y_archivo_ausente(): void
    {
        $pago = $this->crearPagoCargado();

        $this->actingAs($this->userFuncionario, 'sanctum')
            ->getJson("/api/v1/pagos/{$pago->id}/archivo")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('archivo_path');

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/pagos/{$pago->id}/archivo")
            ->assertUnauthorized();

        $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/v1/pagos/{$pago->id}/archivo")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('archivo_path');
    }

    public function test_reemplazo_confirma_nuevo_soporte_y_elimina_anterior(): void
    {
        $pago = $this->crearPagoCargado();
        Storage::disk('local')->put($pago->archivo_path, 'anterior');

        $nuevo = app(PagoSoporteService::class)->cargar(
            $this->solicitud,
            UploadedFile::fake()->createWithContent('nuevo.pdf', '%PDF-nuevo'),
            $this->userFuncionario,
        );

        $this->assertNotSame($pago->archivo_path, $nuevo->archivo_path);
        Storage::disk('local')->assertExists($nuevo->archivo_path);
        Storage::disk('local')->assertMissing($pago->archivo_path);
        $this->assertDatabaseCount('pagos_soportes', 1);
    }

    public function test_si_falla_bd_elimina_archivo_nuevo_y_conserva_anterior(): void
    {
        $pago = $this->crearPagoCargado();
        Storage::disk('local')->put($pago->archivo_path, 'anterior');
        PagoSoporte::updating(fn () => throw new \RuntimeException('fallo de BD simulado'));

        try {
            app(PagoSoporteService::class)->cargar(
                $this->solicitud,
                UploadedFile::fake()->createWithContent('nuevo.pdf', '%PDF-nuevo'),
                $this->userFuncionario,
            );
            $this->fail('La operación debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('fallo de BD simulado', $exception->getMessage());
        } finally {
            PagoSoporte::flushEventListeners();
        }

        Storage::disk('local')->assertExists($pago->archivo_path);
        $this->assertSame([$pago->archivo_path], Storage::disk('local')->allFiles('pagos-soportes'));
        $this->assertSame($pago->archivo_path, $pago->fresh()->archivo_path);
    }

    public function test_si_falla_eliminacion_del_anterior_conserva_registro_nuevo_y_audita(): void
    {
        $pago = $this->crearPagoCargado();
        $rutaNueva = "pagos-soportes/{$this->solicitud->id}/nuevo-id.pdf";
        $disco = \Mockery::mock();
        $disco->shouldReceive('putFileAs')->once()->andReturn($rutaNueva);
        $disco->shouldReceive('exists')->once()->with($pago->archivo_path)->andReturnTrue();
        $disco->shouldReceive('delete')->once()->with($pago->archivo_path)->andReturnFalse();
        Storage::shouldReceive('disk')->with('local')->andReturn($disco);

        $nuevo = app(PagoSoporteService::class)->cargar(
            $this->solicitud,
            UploadedFile::fake()->createWithContent('nuevo.pdf', '%PDF-nuevo'),
            $this->userFuncionario,
        );

        $this->assertSame($rutaNueva, $nuevo->fresh()->archivo_path);
        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'eliminar_soporte_anterior_fallido',
            'modelo_id' => $nuevo->id,
        ]);
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
