<?php

namespace Tests\Feature\Security;

class RateLimitSecurityTest extends SecurityTestCase
{
    public function test_login_pair_is_canonicalized_and_retry_after_present(): void
    {
        $user = $this->account();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => strtoupper($user->username), 'password' => 'bad'])->assertUnprocessable();
        }
        $this->loginBrowser($user, 'bad')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_ip_limit_stops_password_spraying(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => 'unknown'.$i, 'password' => 'bad'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['username' => 'unknown-last', 'password' => 'bad'])->assertStatus(429);
    }

    public function test_mfa_has_separate_limit_and_public_validation_is_throttled(): void
    {
        $this->loginBrowser($this->account())->assertOk();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/mfa/verify', ['code' => 'bad'])->assertForbidden();
        }
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => 'bad'])->assertStatus(429);
        for ($i = 0; $i < 30; $i++) { $this->getJson('/api/v1/validar-certificado/synthetic-invalid')->assertNotFound(); }
        $this->getJson('/api/v1/validar-certificado/synthetic-invalid')->assertStatus(429);
    }
}
