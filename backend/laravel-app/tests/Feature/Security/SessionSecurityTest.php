<?php

namespace Tests\Feature\Security;

use App\Services\BackofficeIdentityService;

class SessionSecurityTest extends SecurityTestCase
{
    public function test_password_alone_is_pending_without_authenticated_guard(): void
    {
        $user = $this->account();
        $this->loginBrowser($user)->assertOk()->assertJsonPath('data.status', 'MFA_ENROLLMENT_REQUIRED');
        $this->freshRequest();
        $this->getJson('/api/v1/admin/usuarios-internos')->assertUnauthorized();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.mfa_pending', true);
    }

    public function test_deactivation_role_and_password_reset_revoke_existing_cookie(): void
    {
        $actor = $this->account();
        foreach (['deactivate' => ['motivo' => 'Security test'], 'role' => ['role' => 'secretario'], 'reset' => []] as $action => $data) {
            $target = $this->account();
            $this->enroll($target);
            app(BackofficeIdentityService::class)->transition($target->fresh(), $action, $data, $actor);
            $this->freshRequest();
            $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        }
    }

    public function test_pending_expiry_and_absolute_session_expiry_fail_closed(): void
    {
        $user = $this->account();
        $this->loginBrowser($user)->assertOk();
        $this->withSession(['pending_auth' => ['user_id' => $user->id, 'version' => $user->fresh()->auth_version, 'at' => time() - 601]])
            ->getJson('/api/v1/auth/me')->assertUnauthorized();
        $employee = $this->account('funcionario');
        $this->loginBrowser($employee)->assertOk();
        $this->withSession(['auth_started_at' => time() - 28801])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_server_session(): void
    {
        $this->loginBrowser($this->account('funcionario'))->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        $this->freshRequest();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_password_change_rotates_epoch_and_preserves_the_current_browser_session(): void
    {
        $user = $this->account('funcionario', true);
        $this->loginBrowser($user)->assertOk();
        $epoch = $user->fresh()->auth_version;
        $this->putJson('/api/v1/auth/change-password', [
            'current_password' => 'Personal secure phrase 2026',
            'password' => 'Another secure phrase 2026', 'password_confirmation' => 'Another secure phrase 2026',
        ])->assertOk();
        $this->freshRequest();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.must_change_password', false);
        $this->assertGreaterThan($epoch, $user->fresh()->auth_version);
    }

    public function test_second_login_revokes_prior_epoch(): void
    {
        $user = $this->account('funcionario');
        $this->loginBrowser($user)->assertOk();
        $epoch = $user->fresh()->auth_version;
        $this->loginBrowser($user->fresh())->assertOk();
        $this->withSession(['auth_version' => $epoch])->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
