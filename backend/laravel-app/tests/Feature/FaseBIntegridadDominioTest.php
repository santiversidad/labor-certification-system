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
use App\Models\RangoSalarial;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);
        $this->secretario = User::factory()->create(['estado' => true]);
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
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
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

    public function test_funcionario_sin_historial_puede_eliminarse_temporalmente(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/funcionarios/{$this->funcionario->id}")
            ->assertOk();

        $this->assertDatabaseMissing('funcionarios', ['id' => $this->funcionario->id]);
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
        $solicitud = $this->solicitudAprobada(false);
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

    public function test_generacion_con_salario_falla_si_no_hay_asignacion_vigente(): void
    {
        $solicitud = $this->solicitudAprobada(true);

        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertConflict()
            ->assertJsonPath('code', 'CERTIFICATE_SOURCE_CONFLICT');

        $this->assertDatabaseCount('certificados', 0);
        $this->assertDatabaseHas('audit_logs', ['accion' => 'generar_certificado_fallido']);
    }

    public function test_generacion_con_salario_falla_si_hay_asignaciones_ambiguas(): void
    {
        $this->asignarCargo('2020-01-01');
        $this->asignarCargo('2021-01-01');
        RangoSalarial::create([
            'codigo' => '219', 'grado' => '02', 'vigencia_anio' => now()->year,
            'salario_basico' => 4000000, 'moneda' => 'COP', 'estado' => true,
        ]);

        $solicitud = $this->solicitudAprobada(true);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertConflict();

        $this->assertDatabaseCount('certificados', 0);
    }

    public function test_generacion_con_salario_falla_si_no_hay_rango_y_sin_salario_no_lo_invoca(): void
    {
        $this->asignarCargo('2020-01-01');
        $conSalario = $this->solicitudAprobada(true);

        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$conSalario->id}/generar-certificado")
            ->assertConflict();

        $sinSalario = $this->solicitudAprobada(false);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$sinSalario->id}/generar-certificado")
            ->assertCreated()
            ->assertJsonPath('data.certificado.snapshot_schema_version', 1);
    }

    public function test_snapshot_emitido_no_puede_modificarse(): void
    {
        $solicitud = $this->solicitudAprobada(false);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        $certificado = Certificado::firstOrFail();
        $this->expectException(\DomainException::class);
        $certificado->update(['snapshot_datos' => ['alterado' => true]]);
    }

    private function solicitudAprobada(bool $requiereSalario): SolicitudCertificacion
    {
        return SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => TipoCertificadoEnum::Laboral,
            'estado' => EstadoSolicitudEnum::Aprobada,
            'requiere_salario' => $requiereSalario,
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
        ]);
    }
}
