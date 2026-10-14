<?php

namespace Tests\Feature\Security;

use App\Models\Funcionario;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

abstract class SecurityTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        config(['security.api_tokens_enabled' => false]);
        $this->withHeader('Origin', 'http://localhost:3200');
    }

    protected function account(string $role = 'admin', bool $forced = false): User
    {
        $user = ($role === 'funcionario' ? User::factory() : User::factory()->backoffice())->create([
            'password' => 'Personal secure phrase 2026', 'must_change_password' => $forced,
        ]);
        $user->assignRole($role);
        if ($role === 'funcionario') {
            Funcionario::create(['user_id' => $user->id, 'numero_documento' => $user->documento,
                'nombres' => 'Persona', 'apellidos' => 'Sintética', 'estado' => 'activo']);
        }
        return $user->fresh();
    }

    protected function loginBrowser(User $user, string $password = 'Personal secure phrase 2026')
    {
        Auth::forgetGuards();
        return $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => $password]);
    }

    protected function freshRequest(): void
    {
        Auth::forgetGuards();
    }

    protected function enroll(User $user): array
    {
        $this->loginBrowser($user)->assertOk();
        $secret = $this->postJson('/api/v1/auth/mfa/setup')->assertOk()->json('data.secret');
        $code = app(Google2FA::class)->getCurrentOtp($secret);
        return $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $code])->assertOk()->json('data.recovery_codes');
    }
}
