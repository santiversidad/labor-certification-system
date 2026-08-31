<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Cargo;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\ManualFuncionVersion;
use App\Models\User;
use App\Services\ResolverFuncionesCargoService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualFuncionesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $funcionario;

    private Cargo $cargo;

    private Cargo $otroCargo;

    private ManualFuncion $manual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);
        $this->funcionario = User::factory()->create(['estado' => true]);
        $this->funcionario->assignRole(RoleEnum::Funcionario->value);
        $this->cargo = Cargo::create([
            'codigo' => '219', 'grado' => '02', 'denominacion' => 'Profesional', 'estado' => true,
        ]);
        $this->otroCargo = Cargo::create([
            'codigo' => '407', 'grado' => '01', 'denominacion' => 'Auxiliar', 'estado' => true,
        ]);
        $this->manual = ManualFuncion::create(['codigo' => 'MEF-001', 'nombre' => 'Manual institucional']);
    }

    public function test_resuelve_version_vigente_y_no_mezcla_funciones_de_otro_cargo(): void
    {
        $vigente = $this->crearVersion('2026', '2026-01-01', null);
        $this->agregarCargo($vigente, $this->cargo, 'Función cargo correcto');
        $this->agregarCargo($vigente, $this->otroCargo, 'Función de otro cargo');

        $resultado = app(ResolverFuncionesCargoService::class)->resolver(
            $this->cargo->id,
            CarbonImmutable::parse('2026-08-30'),
        );

        $this->assertSame('2026', $resultado['version']);
        $this->assertSame('Función cargo correcto', $resultado['funciones'][0]['descripcion']);
        $this->assertNotContains('Función de otro cargo', array_column($resultado['funciones'], 'descripcion'));
    }

    public function test_version_futura_y_vencida_no_aplican(): void
    {
        $futura = $this->crearVersion('2027', '2027-01-01', null);
        $this->agregarCargo($futura, $this->cargo, 'Futura');
        $this->assertNull(app(ResolverFuncionesCargoService::class)->resolver(
            $this->cargo->id,
            CarbonImmutable::parse('2026-08-30'),
        ));

        $futura->update(['estado' => 'inactivo']);
        $vencida = $this->crearVersion('2025', '2025-01-01', '2025-12-31');
        $this->agregarCargo($vencida, $this->cargo, 'Vencida');
        $this->assertNull(app(ResolverFuncionesCargoService::class)->resolver(
            $this->cargo->id,
            CarbonImmutable::parse('2026-08-30'),
        ));
    }

    public function test_funcionario_no_administra_manual_y_admin_si_puede(): void
    {
        $this->actingAs($this->funcionario, 'sanctum')
            ->getJson('/api/v1/manual-funciones')->assertForbidden();
        $this->actingAs($this->funcionario, 'sanctum')
            ->postJson('/api/v1/manual-funciones', [
                'codigo' => 'NO', 'nombre' => 'No autorizado',
            ])->assertForbidden();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/manual-funciones', [
                'codigo' => 'MEF-002', 'nombre' => 'Segundo manual',
            ])->assertCreated();

        $manualId = $response->json('data.id');
        $version = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/manual-funciones/{$manualId}/versiones", [
                'version' => '1.0',
                'vigencia_desde' => '2026-01-01',
                'acto_tipo' => 'Decreto',
                'acto_numero' => '123',
                'acto_fecha' => '2025-12-20',
            ])->assertCreated();

        $versionId = $version->json('data.id');
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/manual-funciones/versiones/{$versionId}/cargos/{$this->cargo->id}", [
                'proposito_principal' => 'Gestionar el proceso.',
                'funciones' => [
                    ['orden' => 1, 'descripcion' => 'Ejecutar las funciones asignadas.'],
                ],
            ])->assertOk();
        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/manual-funciones/versiones/{$versionId}/publicar")
            ->assertOk()->assertJsonPath('data.estado', 'publicado');

        $this->assertDatabaseHas('audit_logs', ['accion' => 'publicar_manual_funciones']);
    }

    public function test_version_rechaza_fechas_invalidas_y_ordenes_no_positivos_o_duplicados(): void
    {
        $version = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/manual-funciones/{$this->manual->id}/versiones", [
                'version' => 'invalida',
                'vigencia_desde' => '2026-12-31',
                'vigencia_hasta' => '2026-01-01',
                'acto_tipo' => 'Decreto',
                'acto_numero' => 'X',
                'acto_fecha' => '2026-01-01',
            ])->assertUnprocessable();
        $this->assertNull($version->json('data'));

        $borrador = $this->manual->versiones()->create([
            'version' => 'borrador', 'vigencia_desde' => '2026-01-01',
            'acto_tipo' => 'Decreto', 'acto_numero' => 'B', 'acto_fecha' => '2026-01-01',
            'estado' => 'borrador',
        ]);

        foreach ([
            [['orden' => 0, 'descripcion' => 'Inválida']],
            [
                ['orden' => 1, 'descripcion' => 'Uno'],
                ['orden' => 1, 'descripcion' => 'Duplicada'],
            ],
        ] as $funciones) {
            $this->actingAs($this->admin, 'sanctum')
                ->putJson("/api/v1/manual-funciones/versiones/{$borrador->id}/cargos/{$this->cargo->id}", [
                    'proposito_principal' => 'Propósito',
                    'funciones' => $funciones,
                ])->assertUnprocessable();
        }
    }

    public function test_no_publica_cargo_sin_funciones_y_no_edita_version_publicada(): void
    {
        $borrador = $this->manual->versiones()->create([
            'version' => 'sin-funciones', 'vigencia_desde' => '2028-01-01',
            'acto_tipo' => 'Decreto', 'acto_numero' => 'SF', 'acto_fecha' => '2027-12-01',
            'estado' => 'borrador',
        ]);
        ManualCargoVersion::create([
            'manual_funciones_version_id' => $borrador->id,
            'cargo_id' => $this->cargo->id,
            'proposito_principal' => 'Propósito',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/manual-funciones/versiones/{$borrador->id}/publicar")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MANUAL_CARGO_WITHOUT_FUNCTIONS');

        $publicada = $this->crearVersion('publicada', '2029-01-01', null);
        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/manual-funciones/versiones/{$publicada->id}", [
                'version' => 'alterada',
                'vigencia_desde' => '2029-01-01',
                'acto_tipo' => 'Decreto',
                'acto_numero' => 'P',
                'acto_fecha' => '2028-12-01',
            ])->assertConflict()
            ->assertJsonPath('code', 'MANUAL_VERSION_IMMUTABLE');
    }

    private function crearVersion(string $version, string $desde, ?string $hasta): ManualFuncionVersion
    {
        return $this->manual->versiones()->create([
            'version' => $version,
            'vigencia_desde' => $desde,
            'vigencia_hasta' => $hasta,
            'acto_tipo' => 'Decreto',
            'acto_numero' => $version,
            'acto_fecha' => $desde,
            'estado' => 'publicado',
            'created_by' => $this->admin->id,
        ]);
    }

    private function agregarCargo(ManualFuncionVersion $version, Cargo $cargo, string $descripcion): void
    {
        $cargoVersion = ManualCargoVersion::create([
            'manual_funciones_version_id' => $version->id,
            'cargo_id' => $cargo->id,
            'proposito_principal' => 'Propósito',
        ]);
        $cargoVersion->funciones()->create(['orden' => 1, 'descripcion' => $descripcion]);
    }
}
