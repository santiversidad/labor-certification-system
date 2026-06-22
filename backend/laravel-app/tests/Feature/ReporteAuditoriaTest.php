<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_consultar_resumen_reportes(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $admin = User::factory()->create(['estado' => true]);
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/reportes/resumen')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_funcionarios',
                    'total_solicitudes',
                    'solicitudes_pendientes',
                    'solicitudes_aprobadas',
                    'solicitudes_rechazadas',
                    'certificados_generados',
                    'pagos_pendientes',
                    'tiempo_promedio_respuesta',
                ],
            ]);
    }

    public function test_funcionario_no_puede_consultar_reportes(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $funcionario = User::factory()->create(['estado' => true]);
        $funcionario->assignRole(RoleEnum::Funcionario->value);

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/v1/reportes/resumen')
            ->assertForbidden();
    }

    public function test_admin_puede_filtrar_auditoria(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $admin = User::factory()->create(['estado' => true]);
        $admin->assignRole(RoleEnum::Admin->value);

        AuditLog::create([
            'user_id' => $admin->id,
            'accion' => 'generar_certificado',
            'modelo' => 'Certificado',
            'modelo_id' => 1,
            'descripcion' => 'Prueba',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/auditoria?accion=generar_certificado&entidad=Certificado')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.accion', 'generar_certificado');
    }
}
