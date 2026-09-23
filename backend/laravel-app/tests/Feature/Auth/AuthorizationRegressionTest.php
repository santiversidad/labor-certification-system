<?php

namespace Tests\Feature\Auth;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\RoleEnum;
use App\Models\Cargo;
use App\Models\Funcionario;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN_ENDPOINTS = [
        '/api/v1/funcionarios',
        '/api/v1/cargos',
        '/api/v1/solicitudes',
        '/api/v1/certificados',
        '/api/v1/reportes',
        '/api/v1/configuracion/certificaciones',
        '/api/v1/manual-funciones/estado',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
    }

    public function test_auth_me_identifica_admin_secretario_y_funcionario(): void
    {
        foreach ([RoleEnum::Admin, RoleEnum::Secretario, RoleEnum::Funcionario] as $role) {
            $user = User::factory()->create([
                'password' => bcrypt('password'),
                'must_change_password' => false,
            ]);
            $user->assignRole($role->value);
            if ($role === RoleEnum::Funcionario) {
                $this->vincularFuncionario($user);
            }

            $token = $this->loginToken($user);
            $this->withToken($token)->getJson('/api/v1/auth/me')
                ->assertOk()
                ->assertJsonPath('data.roles.0', $role->value);
            $this->flushHeaders();
        }
    }

    public function test_auth_me_permanece_disponible_durante_cambio_obligatorio(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'must_change_password' => true,
        ]);
        $user->assignRole(RoleEnum::Funcionario->value);
        $this->vincularFuncionario($user);

        $this->withToken($this->loginToken($user))->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonMissingPath('data.funcionario');
    }

    public function test_auth_me_rechaza_anonimo_con_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_suite_usa_cache_aislado_para_no_contaminar_spatie_local(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.pgsql.database'));
    }

    public function test_admin_tiene_acceso_a_toda_la_matriz_administrativa(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole(RoleEnum::Admin->value);

        foreach (self::ADMIN_ENDPOINTS as $endpoint) {
            $this->actingAs($admin, 'sanctum')->getJson($endpoint)->assertOk();
        }
    }

    public function test_funcionario_conserva_403_administrativo_y_su_disponibilidad(): void
    {
        $funcionario = $this->crearFuncionario();

        foreach (self::ADMIN_ENDPOINTS as $endpoint) {
            $this->actingAs($funcionario, 'sanctum')->getJson($endpoint)->assertForbidden();
        }

        $this->actingAs($funcionario, 'sanctum')
            ->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertOk();
    }

    public function test_secretario_conserva_solo_su_matriz_definida(): void
    {
        $secretario = User::factory()->create(['must_change_password' => false]);
        $secretario->assignRole(RoleEnum::Secretario->value);

        foreach ([
            '/api/v1/funcionarios',
            '/api/v1/cargos',
            '/api/v1/solicitudes',
            '/api/v1/certificados',
            '/api/v1/reportes',
        ] as $endpoint) {
            $this->actingAs($secretario, 'sanctum')->getJson($endpoint)->assertOk();
        }

        foreach ([
            '/api/v1/configuracion/certificaciones',
            '/api/v1/manual-funciones/estado',
        ] as $endpoint) {
            $this->actingAs($secretario, 'sanctum')->getJson($endpoint)->assertForbidden();
        }
    }

    private function loginToken(User $user): string
    {
        Auth::forgetGuards();
        $response = $this->postJson('/api/v1/auth/login', [
            'cedula' => $user->documento,
            'password' => 'password',
        ])->assertOk();
        Auth::forgetGuards();

        return $response->json('data.token');
    }

    private function crearFuncionario(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->assignRole(RoleEnum::Funcionario->value);
        $this->vincularFuncionario($user);

        return $user->fresh();
    }

    private function vincularFuncionario(User $user): void
    {
        $cargo = Cargo::firstOrCreate([
            'codigo' => 'AUTH',
            'grado' => '01',
        ], [
            'denominacion' => 'Cargo de autorización',
            'nivel' => 'Profesional',
            'estado' => true,
        ]);
        Funcionario::create([
            'user_id' => $user->id,
            'tipo_documento' => 'CC',
            'numero_documento' => $user->documento,
            'nombres' => 'Prueba',
            'apellidos' => 'Autorización',
            'estado' => EstadoFuncionarioEnum::Activo,
            'fecha_ingreso' => '2020-01-01',
            'dependencia' => 'Pruebas',
            'cargo_id' => $cargo->id,
        ]);
    }
}
