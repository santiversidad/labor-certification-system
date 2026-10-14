<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Hash;

class AuthenticationSecurityTest extends SecurityTestCase
{
    public function test_browser_login_returns_no_token_and_employee_has_cookie(): void
    {
        $user = $this->account('funcionario');
        $this->loginBrowser($user)->assertOk()->assertJsonMissingPath('data.token')->assertCookie(config('session.cookie'));
        $this->assertSame(0, $user->tokens()->count());
        $this->freshRequest();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_invalid_unknown_and_disabled_credentials_have_same_response(): void
    {
        $user = $this->account();
        $wrong = $this->loginBrowser($user, 'bad')->assertUnprocessable()->json();
        $user->estado = false; $user->save();
        $inactive = $this->loginBrowser($user)->assertUnprocessable()->json();
        $unknown = $this->postJson('/api/v1/auth/login', ['username' => 'unknown.person', 'password' => 'bad'])->assertUnprocessable()->json();
        $this->assertSame($wrong, $inactive); $this->assertSame($wrong, $unknown);
    }

    public function test_old_bearer_cannot_authenticate_browser_even_when_tokens_enabled(): void
    {
        $user = $this->account();
        config(['security.api_tokens_enabled' => true]);
        $token = $user->createToken('synthetic', ['*'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_unknown_origin_cannot_use_login_or_cookie(): void
    {
        $this->getJson('/api/v1/auth/me', ['Origin' => 'https://untrusted.invalid'])->assertForbidden();
        $this->postJson('/api/v1/auth/login', ['username' => 'none', 'password' => 'bad'], ['Origin' => 'https://untrusted.invalid'])->assertForbidden();
    }

    public function test_password_policy_accepts_passphrase_rejects_common_and_requires_15_before_mfa(): void
    {
        $user = $this->account('admin', true);
        $this->loginBrowser($user)->assertOk();
        foreach (['short phrase', '123456789012345'] as $password) {
            $this->putJson('/api/v1/auth/change-password', ['current_password' => 'Personal secure phrase 2026', 'password' => $password, 'password_confirmation' => $password])->assertUnprocessable();
        }
        $password = 'una frase amplia con espacios';
        $this->putJson('/api/v1/auth/change-password', ['current_password' => 'Personal secure phrase 2026', 'password' => $password, 'password_confirmation' => $password])->assertOk();
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->getJson('/api/v1/admin/usuarios-internos')->assertUnauthorized();
    }

    public function test_argon2id_and_existing_bcrypt_rehash_progressively(): void
    {
        $user = $this->account('funcionario');
        $this->assertSame('bcrypt', Hash::info($user->password)['algoName']);
        config(['hashing.driver' => 'argon2id']);
        Hash::clearResolvedInstance('hash'); app()->forgetInstance('hash');
        $this->loginBrowser($user)->assertOk();
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
        $this->assertTrue(password_verify('Personal secure phrase 2026', $user->fresh()->password));
    }
}
