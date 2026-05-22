<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\RangoSalarial;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RangoSalarialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);

        $this->admin = User::factory()->create(['estado' => true]);
        $this->admin->assignRole(RoleEnum::Admin->value);
    }

    public function test_consultar_rango_salarial_existente(): void
    {
        RangoSalarial::factory()->create([
            'codigo'        => '219',
            'grado'         => '02',
            'vigencia_anio' => 2026,
            'salario_basico' => 3450000,
            'estado'        => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/rangos-salariales/consultar?codigo=219&grado=02&vigencia=2026');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', '219')
            ->assertJsonPath('data.grado', '02')
            ->assertJsonPath('data.vigencia_anio', 2026);
    }

    public function test_consultar_rango_salarial_inexistente_retorna_404(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/rangos-salariales/consultar?codigo=999&grado=99&vigencia=2026');

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_crear_rango_salarial(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/rangos-salariales', [
                'codigo'         => '220',
                'grado'          => '03',
                'vigencia_anio'  => 2026,
                'salario_basico' => 2800000,
                'moneda'         => 'COP',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', '220');

        $this->assertDatabaseHas('rangos_salariales', [
            'codigo'        => '220',
            'grado'         => '03',
            'vigencia_anio' => 2026,
        ]);
    }

    public function test_no_se_puede_duplicar_rango_salarial(): void
    {
        RangoSalarial::factory()->create([
            'codigo'        => '219',
            'grado'         => '02',
            'vigencia_anio' => 2026,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/rangos-salariales', [
                'codigo'         => '219',
                'grado'          => '02',
                'vigencia_anio'  => 2026,
                'salario_basico' => 3500000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['vigencia_anio']);
    }

    public function test_listar_rangos_salariales_requiere_autenticacion(): void
    {
        $this->getJson('/api/v1/rangos-salariales')
            ->assertUnauthorized();
    }
}
