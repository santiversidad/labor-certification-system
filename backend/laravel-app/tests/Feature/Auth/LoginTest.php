<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleEnum;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    public function test_usuario_puede_iniciar_sesion_con_cedula_y_password_correctos(): void
    {
        $user = User::factory()->create([
            'documento' => '12345678',
            'password'  => bcrypt('password'),
            'estado'    => true,
        ]);
        $user->assignRole(RoleEnum::Admin->value);

        $response = $this->postJson('/api/v1/auth/login', [
            'cedula'   => '12345678',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => ['id', 'documento', 'roles'],
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_login_falla_con_cedula_inexistente(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'cedula'   => '99999999',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cedula']);
    }

    public function test_login_falla_con_password_incorrecta(): void
    {
        User::factory()->create([
            'documento' => '12345678',
            'password'  => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'cedula'   => '12345678',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cedula']);
    }

    public function test_login_rechaza_usuario_inactivo(): void
    {
        User::factory()->create([
            'documento' => '12345678',
            'password'  => bcrypt('password'),
            'estado'    => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'cedula'   => '12345678',
            'password' => 'password',
        ]);

        $response->assertForbidden()
            ->assertJson(['success' => false]);
    }

    public function test_login_valida_que_cedula_y_password_son_requeridos(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cedula', 'password']);
    }

    public function test_usuario_autenticado_puede_obtener_sus_datos(): void
    {
        $user = User::factory()->create([
            'documento' => '12345678',
            'estado'    => true,
        ]);
        $user->assignRole(RoleEnum::Funcionario->value);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.documento', '12345678');
    }

    public function test_usuario_puede_cerrar_sesion(): void
    {
        $user = User::factory()->create(['estado' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_ruta_protegida_rechaza_peticion_sin_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
