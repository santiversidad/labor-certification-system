<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoCertificadoEnum;
use App\Enums\TipoVinculacionEnum;
use App\Models\Cargo;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Services\ExpedirCertificacionService;
use App\Services\GenerarCertificadoService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ManualFixture;
use Tests\TestCase;

class FaseBIntegridadDominioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretario;

    private User $funcionarioUser;

    private Cargo $cargo;

    private Funcionario $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->backoffice()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);
        $this->secretario = User::factory()->backoffice()->create(['estado' => true]);
        $this->secretario->assignRole(RoleEnum::Secretario->value);
        $this->funcionarioUser = User::factory()->create(['estado' => true]);
        $this->funcionarioUser->assignRole(RoleEnum::Funcionario->value);
        $this->cargo = Cargo::create([
            'codigo' => '219', 'grado' => '02', 'denominacion' => 'Profesional', 'estado' => true,
        ]);
        $this->funcionario = Funcionario::create([
            'user_id' => $this->funcionarioUser->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '123456789',
            'nombres' => 'Ana',
            'apellidos' => 'Prueba',
            'estado' => EstadoFuncionarioEnum::Activo,
            'cargo_id' => $this->cargo->id,
        ]);
    }

    public function test_funcionario_con_expediente_no_puede_eliminarse_y_el_intento_se_audita(): void
    {
        SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Sencillo,
            'estado' => EstadoSolicitudEnum::Pendiente,
            'created_by' => $this->funcionarioUser->id,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/funcionarios/{$this->funcionario->id}")
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'EMPLOYEE_HAS_HISTORY');

        $this->assertDatabaseHas('funcionarios', ['id' => $this->funcionario->id]);
        $this->assertDatabaseHas('solicitudes_certificacion', ['funcionario_id' => $this->funcionario->id]);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'eliminar_funcionario_bloqueado']);
    }

    public function test_funcionario_sin_historial_tampoco_se_elimina(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/funcionarios/{$this->funcionario->id}")
            ->assertStatus(410)->assertJsonPath('code', 'EMPLOYEE_DELETE_DISABLED');

        $this->assertDatabaseHas('funcionarios', ['id' => $this->funcionario->id]);
    }

    public function test_cardinalidad_un_usuario_un_funcionario_se_impone_en_bd(): void
    {
        $this->expectException(QueryException::class);
        Funcionario::create([
            'user_id' => $this->funcionarioUser->id,
            'tipo_documento' => 'CC',
            'numero_documento' => '987654321',
            'nombres' => 'Duplicado',
            'apellidos' => 'Usuario',
            'estado' => EstadoFuncionarioEnum::Activo,
        ]);
    }

    public function test_cardinalidad_un_pago_logico_por_solicitud_se_impone_en_bd(): void
    {
        $solicitud = $this->solicitudAprobada();
        $datos = [
            'solicitud_certificacion_id' => $solicitud->id,
            'funcionario_id' => $this->funcionario->id,
            'archivo_path' => 'pagos-soportes/uno.pdf',
            'archivo_original_nombre' => 'uno.pdf',
            'estado' => 'cargado',
        ];
        PagoSoporte::create($datos);

        $this->expectException(QueryException::class);
        PagoSoporte::create([
            ...$datos,
            'archivo_path' => 'pagos-soportes/dos.pdf',
            'archivo_original_nombre' => 'dos.pdf',
        ]);
    }

    public function test_generacion_sencilla_falla_si_no_hay_asignacion_vigente(): void
    {
        try {
            app(ExpedirCertificacionService::class)->expedir($this->funcionarioUser, 'sencillo', null);
            $this->fail('Debe rechazar la ausencia de asignación.');
        } catch (\DomainException $error) {
            $this->assertSame('ASIGNACION_NO_VIGENTE', $error->getMessage());
        }
        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'generacion_certificado_fallida']);
    }

    public function test_generacion_sencilla_falla_si_hay_asignaciones_ambiguas(): void
    {
        $this->asignarCargo('2020-01-01');
        $this->asignarCargo('2021-01-01');
        $this->expectException(\DomainException::class);
        app(ExpedirCertificacionService::class)->expedir($this->funcionarioUser, 'sencillo', null);
    }

    public function test_generacion_sencilla_no_exige_ficha_manual(): void
    {
        FuncionarioCargo::create([
            'funcionario_id' => $this->funcionario->id,
            'cargo_id' => $this->cargo->id,
            'tipo_vinculacion' => TipoVinculacionEnum::Planta,
            'naturaleza_cargo' => NaturalezaCargoEnum::CarreraAdministrativa,
            'es_cargo_base' => true,
            'fecha_inicio' => '2020-01-01',
        ]);
        $resultado = app(ExpedirCertificacionService::class)->expedir($this->funcionarioUser, 'sencillo', null);
        $this->assertSame(3, $resultado['certificado']->snapshot_schema_version);
    }

    public function test_snapshot_emitido_no_puede_modificarse(): void
    {
        ManualFixture::vincular($this->funcionario);
        $solicitud = $this->solicitudAprobada();
        app(GenerarCertificadoService::class)->generar($solicitud, $this->funcionarioUser);

        $certificado = Certificado::firstOrFail();
        $this->expectException(\DomainException::class);
        $certificado->update(['snapshot_datos' => ['alterado' => true]]);
    }

    private function solicitudAprobada(TipoCertificadoEnum $tipo = TipoCertificadoEnum::Sencillo): SolicitudCertificacion
    {
        return SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => $tipo,
            'estado' => EstadoSolicitudEnum::Aprobada,
            'created_by' => $this->funcionarioUser->id,
        ]);
    }

    private function asignarCargo(string $fechaInicio): void
    {
        FuncionarioCargo::create([
            'funcionario_id' => $this->funcionario->id,
            'cargo_id' => $this->cargo->id,
            'tipo_vinculacion' => TipoVinculacionEnum::Planta,
            'naturaleza_cargo' => NaturalezaCargoEnum::CarreraAdministrativa,
            'es_cargo_base' => true,
            'fecha_inicio' => $fechaInicio,
            'manual_cargo_version_id' => ManualFixture::ficha($this->cargo)->id,
        ]);
    }
}
