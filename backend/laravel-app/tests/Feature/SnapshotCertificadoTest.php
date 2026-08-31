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
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\RangoSalarial;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SnapshotCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $secretario;

    private User $funcionarioUser;

    private Funcionario $funcionario;

    private Cargo $cargo;

    private ManualCargoVersion $cargoVersion;

    private RangoSalarial $rango;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        Storage::fake('local');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30 09:00:00', 'America/Bogota'));

        $this->secretario = User::factory()->create(['estado' => true]);
        $this->secretario->assignRole(RoleEnum::Secretario->value);
        $this->funcionarioUser = User::factory()->create(['estado' => true]);
        $this->funcionarioUser->assignRole(RoleEnum::Funcionario->value);
        $this->cargo = Cargo::create([
            'codigo' => '219', 'grado' => '02', 'denominacion' => 'Profesional Universitario',
            'nivel' => 'Profesional', 'dependencia' => 'Talento Humano', 'estado' => true,
        ]);
        $this->funcionario = Funcionario::create([
            'user_id' => $this->funcionarioUser->id, 'tipo_documento' => 'CC',
            'numero_documento' => '123456789', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
            'estado' => EstadoFuncionarioEnum::Activo, 'fecha_ingreso' => '2020-01-15',
            'dependencia' => 'Talento Humano', 'cargo_id' => $this->cargo->id,
        ]);
        FuncionarioCargo::create([
            'funcionario_id' => $this->funcionario->id,
            'cargo_id' => $this->cargo->id,
            'tipo_vinculacion' => TipoVinculacionEnum::Planta,
            'naturaleza_cargo' => NaturalezaCargoEnum::CarreraAdministrativa,
            'es_cargo_base' => true,
            'fecha_inicio' => '2020-01-15',
        ]);
        $this->rango = RangoSalarial::create([
            'codigo' => '219', 'grado' => '02', 'vigencia_anio' => 2026,
            'salario_basico' => 5000000, 'moneda' => 'COP', 'estado' => true,
        ]);
        $manual = ManualFuncion::create(['codigo' => 'MEF-001', 'nombre' => 'Manual oficial']);
        $version = $manual->versiones()->create([
            'version' => '2026', 'vigencia_desde' => '2026-01-01',
            'acto_tipo' => 'Decreto', 'acto_numero' => '100', 'acto_fecha' => '2025-12-20',
            'estado' => 'publicado',
        ]);
        $this->cargoVersion = ManualCargoVersion::create([
            'manual_funciones_version_id' => $version->id, 'cargo_id' => $this->cargo->id,
            'proposito_principal' => 'Gestionar el talento humano.',
        ]);
        $this->cargoVersion->funciones()->create([
            'orden' => 1, 'descripcion' => 'Administrar los procesos asignados.',
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_snapshot_con_salario_y_funciones_permanece_inmutable_ante_cambios_vivos(): void
    {
        $solicitud = $this->crearSolicitud(true, TipoCertificadoEnum::Funciones);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        $certificado = Certificado::firstOrFail();
        $snapshotOriginal = $certificado->snapshot_datos;
        $this->assertSame('5000000.00', $snapshotOriginal['salario']['valor']);
        $this->assertSame(
            'Administrar los procesos asignados.',
            $snapshotOriginal['manual_funciones']['funciones'][0]['descripcion'],
        );
        $this->assertStringContainsString('Salario basico: 5000000.00 COP', Storage::disk('local')->get($certificado->archivo_pdf_path));

        $this->cargo->update(['denominacion' => 'Cargo modificado']);
        $this->rango->update(['salario_basico' => 9999999]);
        $this->cargoVersion->funciones()->firstOrFail()->update(['descripcion' => 'Función modificada']);

        $this->assertSame($snapshotOriginal, $certificado->fresh()->snapshot_datos);
    }

    public function test_certificado_sin_salario_no_guarda_ni_renderiza_valor_salarial(): void
    {
        $solicitud = $this->crearSolicitud(false, TipoCertificadoEnum::Laboral);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        $certificado = Certificado::firstOrFail();
        $this->assertArrayNotHasKey('salario', $certificado->snapshot_datos);
        $this->assertStringNotContainsString('Salario basico:', Storage::disk('local')->get($certificado->archivo_pdf_path));
        $this->assertSame(1, $certificado->snapshot_schema_version);
    }

    private function crearSolicitud(bool $requiereSalario, TipoCertificadoEnum $tipo): SolicitudCertificacion
    {
        return SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => $tipo,
            'estado' => EstadoSolicitudEnum::Aprobada,
            'requiere_salario' => $requiereSalario,
            'created_by' => $this->funcionarioUser->id,
        ]);
    }
}
