<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\MfaService;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

class MfaSecurityTest extends SecurityTestCase
{
    public function test_enrollment_requires_password_change_and_valid_proof(): void
    {
        $user = $this->account('admin', true);
        $this->loginBrowser($user)->assertOk();
        $this->postJson('/api/v1/auth/mfa/setup')->assertForbidden();
        $this->putJson('/api/v1/auth/change-password', ['current_password' => 'Personal secure phrase 2026', 'password' => 'una segunda frase segura', 'password_confirmation' => 'una segunda frase segura'])->assertOk();
        $secret = $this->postJson('/api/v1/auth/mfa/setup')->assertOk()->json('data.secret');
        $this->postJson('/api/v1/auth/mfa/confirm', ['code' => 'invalid'])->assertUnprocessable();
        $this->assertNull($user->fresh()->mfa_confirmed_at);
        $this->postJson('/api/v1/auth/mfa/confirm', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->freshRequest();
        $this->getJson('/api/v1/admin/usuarios-internos')->assertOk();
        $this->assertNotSame($secret, DB::table('users')->where('id', $user->id)->value('mfa_secret'));
        $this->assertStringNotContainsString($secret, $user->fresh()->toJson());
    }

    public function test_totp_replay_rejected_and_recovery_is_single_use(): void
    {
        $user = $this->account(); $codes = $this->enroll($user);
        $this->loginBrowser($user->fresh())->assertOk()->assertJsonPath('data.status', 'MFA_REQUIRED');
        $code = app(Google2FA::class)->getCurrentOtp($user->fresh()->mfa_secret);
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $code])->assertUnprocessable();
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertOk();
        $this->assertCount(7, $user->fresh()->mfa_recovery_hashes);
        $this->loginBrowser($user->fresh())->assertOk();
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $codes[0]])->assertUnprocessable();
        $this->assertStringNotContainsString($codes[1], DB::table('users')->where('id', $user->id)->value('mfa_recovery_hashes'));
    }

    public function test_regeneration_revokes_previous_recovery_codes(): void
    {
        $user = $this->account(); $old = $this->enroll($user);
        $this->postJson('/api/v1/auth/mfa/recovery-codes')->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->loginBrowser($user->fresh())->assertOk();
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => $old[0]])->assertUnprocessable();
    }

    public function test_reset_requires_other_admin_recent_auth_and_revokes_cookie(): void
    {
        $target = $this->account('secretario'); $this->enroll($target);
        $actor = $this->account();
        $codes = $this->enroll($actor);
        $this->withSession(['step_up_at' => time() - 301])->postJson('/api/v1/admin/usuarios-internos/'.$target->id.'/reset-mfa')->assertForbidden()->assertJsonPath('code', 'REAUTH_REQUIRED');
        $this->postJson('/api/v1/auth/reauthenticate', ['password' => 'Personal secure phrase 2026', 'code' => $codes[0]])->assertOk();
        $epoch = $target->fresh()->auth_version;
        $this->postJson('/api/v1/admin/usuarios-internos/'.$target->id.'/reset-mfa')->assertOk();
        $this->assertNull($target->fresh()->mfa_confirmed_at);
        $this->assertGreaterThan($epoch, $target->fresh()->auth_version);
        $this->postJson('/api/v1/admin/usuarios-internos/'.$actor->id.'/reset-mfa')->assertStatus(409)->assertJsonPath('code', 'MFA_SELF_RESET_FORBIDDEN');
        $this->loginBrowser($target->fresh())->assertOk()->assertJsonPath('data.status', 'MFA_ENROLLMENT_REQUIRED');
    }

    public function test_employee_cannot_enroll_or_verify_backoffice_mfa(): void
    {
        $this->loginBrowser($this->account('funcionario'))->assertOk();
        $this->postJson('/api/v1/auth/mfa/setup')->assertForbidden();
        $this->postJson('/api/v1/auth/mfa/verify', ['code' => '123456'])->assertForbidden();
    }

    public function test_last_active_admin_mfa_reset_is_protected(): void
    {
        $last = $this->account(); $actor = $this->account(); $actor->estado = false; $actor->save();
        // An inactive actor fails policy before any target mutation.
        try { app(MfaService::class)->reset($last, $actor); $this->fail('must reject'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); $this->assertNull($last->fresh()->mfa_confirmed_at); }
    }
}
