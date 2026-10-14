<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['security.api_tokens_enabled' => true]); // CLI-created synthetic token capability only.
    }

    public function actingAs($user, $guard = null)
    {
        // Authorization/domain regressions simulate an already completed MFA session.
        // Security tests exercise password/pending/TOTP over actual middleware instead.
        if ($user instanceof \App\Models\User) {
            if ($user->tipo_cuenta === 'backoffice' && ! $user->mfa_confirmed_at) {
                $user->forceFill(['mfa_confirmed_at' => now()])->save();
            }
            $this->withHeader('Origin', 'http://localhost:3200')->withSession([
                'auth_version' => $user->auth_version, 'auth_started_at' => time(),
                'mfa_verified_user' => $user->id, 'step_up_user' => $user->id, 'step_up_at' => time(),
            ]);
        }
        return parent::actingAs($user, $guard);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($uri === '/api/v1/auth/login') {
            unset($this->defaultHeaders['Authorization']);
            $headers += ['Origin' => 'http://localhost:3200'];
        }
        return parent::postJson($uri, $data, $headers, $options);
    }

    public function withToken($token, $type = 'Bearer')
    {
        unset($this->defaultHeaders['Origin']);
        $this->app['session']->flush();
        \Illuminate\Support\Facades\Auth::forgetGuards();
        return parent::withToken($token, $type);
    }
}
