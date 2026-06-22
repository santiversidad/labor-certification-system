<?php

namespace Tests\Feature;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoActuacionAdministrativaEnum;
use App\Models\Funcionario;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActuacionAdministrativaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_puede_crear_actuacion_administrativa(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $admin = User::factory()->create(['estado' => true]);
        $admin->assignRole(RoleEnum::Admin->value);

        $funcionario = Funcionario::create([
            'tipo_documento' => 'CC',
            'numero_documento' => '123456789',
            'nombres' => 'Ana',
            'apellidos' => 'Prueba',
            'estado' => EstadoFuncionarioEnum::Activo,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/actuaciones-administrativas', [
                'funcionario_id' => $funcionario->id,
                'tipo_actuacion' => TipoActuacionAdministrativaEnum::Nombramiento->value,
                'numero_acto' => '001',
                'fecha_acto' => '2026-01-15',
                'descripcion' => 'Nombramiento de prueba.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.tipo_actuacion', TipoActuacionAdministrativaEnum::Nombramiento->value);

        $this->assertDatabaseHas('audit_logs', ['accion' => 'crear_actuacion']);
    }
}
