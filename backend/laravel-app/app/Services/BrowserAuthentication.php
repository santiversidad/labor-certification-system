<?php

namespace App\Services;

use App\Enums\EstadoFuncionarioEnum;
use App\Exceptions\IdentityException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BrowserAuthentication
{
    public function principal(Request $request): User
    {
        $pending = $request->hasSession() ? $request->session()->get('pending_auth') : null;
        $user = $pending ? User::find($pending['user_id'] ?? 0) : Auth::guard('sanctum')->user();
        if (! $user && ! $pending) {
            // An anonymous /me is not a revoked session. Keep its official CSRF
            // token intact so bootstrap cannot race the login initialization.
            throw new IdentityException('UNAUTHENTICATED', 'Inicie sesión nuevamente.', 401);
        }
        if (! $user || ! $user->estado || ($pending && (time() - ($pending['at'] ?? 0) > config('security.pending_minutes') * 60
            || ($pending['version'] ?? null) !== $user->auth_version))) {
            $this->expire($request);
        }
        $user->assertIdentity();
        if (! $pending && $user->currentAccessToken() instanceof \Laravel\Sanctum\TransientToken
            && (! $request->hasSession() || $request->session()->get('auth_version') !== $user->auth_version)) {
            $this->expire($request);
        }
        if ($user->isFuncionarioAccount() && $user->funcionario?->estado !== EstadoFuncionarioEnum::Activo) {
            $this->expire($request);
        }

        return $user;
    }

    public function begin(Request $request, User $user): void
    {
        abort_unless($request->hasSession(), 403);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('auth_version', $user->auth_version);
        $request->session()->put('auth_started_at', time());
        if ($user->isBackofficeAccount()) {
            // No authenticated guard or privileged session exists before second-factor proof.
            $request->session()->put('pending_auth', ['user_id' => $user->id, 'version' => $user->auth_version, 'at' => time()]);
        } else {
            $this->complete($request, $user);
        }
    }

    public function complete(Request $request, User $user): void
    {
        Auth::guard('web')->login($user, false);
        $request->session()->forget(['pending_auth', 'mfa_setup_secret']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('auth_version', $user->auth_version);
        if ($user->isBackofficeAccount()) {
            $request->session()->put('mfa_verified_user', $user->id);
            $request->session()->put('step_up_user', $user->id);
            $request->session()->put('step_up_at', time());
        }
    }

    public function expire(Request $request): never
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        throw new IdentityException('SESSION_REVOKED', 'La sesión expiró. Inicie sesión nuevamente.', 401);
    }
}
