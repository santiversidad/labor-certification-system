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
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ManualFixture;
use Tests\TestCase;

class SnapshotCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private User $secretario;

    private User $funcionarioUser;

    private Funcionario $funcionario;

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
        $cargo = Cargo::create([
            'codigo' => '219', 'grado' => '02', 'denominacion' => 'Profesional Universitario',
            'nivel' => 'Profesional', 'dependencia' => 'Talento Humano', 'estado' => true,
        ]);
        $this->funcionario = Funcionario::create([
            'user_id' => $this->funcionarioUser->id, 'tipo_documento' => 'CC',
            'numero_documento' => '123456789', 'nombres' => 'Ana', 'apellidos' => 'Pérez',
            'estado' => EstadoFuncionarioEnum::Activo, 'fecha_ingreso' => '2020-01-15',
            'dependencia' => 'Talento Humano', 'cargo_id' => $cargo->id,
        ]);
        FuncionarioCargo::create([
            'funcionario_id' => $this->funcionario->id, 'cargo_id' => $cargo->id,
            'tipo_vinculacion' => TipoVinculacionEnum::Planta,
            'naturaleza_cargo' => NaturalezaCargoEnum::CarreraAdministrativa,
            'es_cargo_base' => true, 'fecha_inicio' => '2020-01-15',
        ]);
        ManualFixture::vincular($this->funcionario);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_snapshot_funciones_contiene_manual_y_nunca_salario(): void
    {
        $solicitud = $this->crearSolicitud(TipoCertificadoEnum::Funciones);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        $certificado = Certificado::firstOrFail();
        $snapshot = $certificado->snapshot_datos;
        $this->assertSame('funciones', $snapshot['tipo_certificado']);
        $this->assertSame('Función sintética de pruebas.', $snapshot['manual_funciones']['funciones'][0]['descripcion']);
        $this->assertArrayNotHasKey('salario', $snapshot);
        $this->assertArrayNotHasKey('requiere_salario', $snapshot);
        $this->assertSame(3, $certificado->snapshot_schema_version);
        $this->assertStringNotContainsString('Salario', Storage::disk('local')->get($certificado->archivo_pdf_path));
    }

    public function test_snapshot_sencillo_no_contiene_manual_funciones_ni_salario(): void
    {
        $solicitud = $this->crearSolicitud(TipoCertificadoEnum::Sencillo);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        $certificado = Certificado::firstOrFail();
        $snapshot = $certificado->snapshot_datos;
        $this->assertSame('sencillo', $snapshot['tipo_certificado']);
        $this->assertArrayNotHasKey('manual_funciones', $snapshot);
        $this->assertArrayNotHasKey('salario', $snapshot);
        $this->assertArrayNotHasKey('funciones', $snapshot);
        $this->assertSame(3, $certificado->snapshot_schema_version);
    }

    public function test_snapshot_emitido_permanece_inmutable(): void
    {
        $solicitud = $this->crearSolicitud(TipoCertificadoEnum::Funciones);
        $this->actingAs($this->secretario, 'sanctum')
            ->postJson("/api/v1/solicitudes/{$solicitud->id}/generar-certificado")
            ->assertCreated();

        $this->expectException(\DomainException::class);
        Certificado::firstOrFail()->update(['snapshot_datos' => ['alterado' => true]]);
    }

    private function crearSolicitud(TipoCertificadoEnum $tipo): SolicitudCertificacion
    {
        return SolicitudCertificacion::create([
            'funcionario_id' => $this->funcionario->id,
            'tipo_certificado' => $tipo,
            'estado' => EstadoSolicitudEnum::Aprobada,
            'created_by' => $this->funcionarioUser->id,
        ]);
    }
}
