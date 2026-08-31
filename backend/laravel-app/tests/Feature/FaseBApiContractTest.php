<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaseBApiContractTest extends TestCase
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

    public function test_paginacion_filtros_y_orden_rechazan_valores_invalidos_o_excesivos(): void
    {
        $casos = [
            '/api/v1/cargos?page=0',
            '/api/v1/cargos?per_page=101',
            '/api/v1/cargos?orden=columna_inyectada',
            '/api/v1/cargos?direccion=sideways',
            '/api/v1/solicitudes?estado=inventado',
            '/api/v1/auditoria?fecha_desde=no-es-fecha',
            '/api/v1/auditoria?fecha_desde=2026-09-01&fecha_hasta=2026-08-01',
        ];

        foreach ($casos as $url) {
            $this->actingAs($this->admin, 'sanctum')
                ->getJson($url)
                ->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Los datos suministrados no son válidos.')
                ->assertJsonStructure(['errors']);
        }
    }

    public function test_reporte_mantiene_contrato_snake_case(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/reportes/resumen')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'total_funcionarios',
                'total_solicitudes',
                'solicitudes_pendientes',
                'solicitudes_aprobadas',
                'solicitudes_rechazadas',
                'certificados_generados',
                'pagos_pendientes',
                'tiempo_promedio_respuesta',
            ]])
            ->assertJsonMissingPath('data.totalSolicitudes');
    }

    public function test_errores_api_no_exponen_stack_aunque_debug_este_activo(): void
    {
        config(['app.debug' => true]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/cargos/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $response->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('trace');
    }
}
