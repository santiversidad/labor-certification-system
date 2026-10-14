<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoFuncionarioEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BrowserAuthentication;
use App\Services\SecurityEvents;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RegistrarAuditoriaAction $registrarAuditoria, private readonly BrowserAuthentication $auth) {}

    public function login(Request $request): JsonResponse
    {
        $field = $request->has('username') ? 'username' : 'cedula';
        $request->validate([$field => ['required', 'string', 'max:64'], 'password' => ['required', 'string', 'max:128']]);
        abort_unless($request->hasSession(), 403);
        $username = strtolower(trim((string) $request->input($field)));
        // A fixed valid hash gives unknown identities the same expensive hash path.
        $dummy = '$argon2id$v=19$m=19456,t=2,p=1$TXlxUTdWdG5qN1lpRUVTMw$Wpb9SVczWxMM5g9mf4brbmu6CvDYB0xdBL4vTJCvEiw';
        $candidate = User::where('username', $username)->first();
        $valid = Hash::check($request->password, $candidate?->password ?? $dummy);
        $eligible = $candidate && $candidate->estado && ($candidate->isBackofficeAccount()
            || ($candidate->isFuncionarioAccount() && $candidate->funcionario?->estado === EstadoFuncionarioEnum::Activo));
        if (! $valid || ! $eligible) {
            SecurityEvents::record('login_failed', ['identity_hash' => hash('sha256', $username), 'ip_hash' => hash('sha256', (string) $request->ip()), 'reason' => 'invalid_credentials_or_state']);
            throw ValidationException::withMessages([$field => ['El usuario o contraseña son incorrectos.']]);
        }
        return DB::transaction(function () use ($request, $candidate, $field) {
            $user = User::lockForUpdate()->findOrFail($candidate->id);
            // A concurrent lifecycle transition cannot be undone by an in-flight login.
            if (! $user->estado || $user->auth_version !== $candidate->auth_version || $user->password !== $candidate->password) {
                throw ValidationException::withMessages([$field => ['El usuario o contraseña son incorrectos.']]);
            }
            $user->assertIdentity();
            if (Hash::needsRehash($user->password)) {
                $user->password = Hash::make($request->password);
            }
            $user->last_login_at = now();
            $user->save();
            $user->revokeAuthentication();
            $this->auth->begin($request, $user);
            $this->registrarAuditoria->execute(accion: 'login', modelo: 'User', modeloId: $user->id,
                descripcion: 'Validación de credenciales de acceso.', actorId: $user->id);
            SecurityEvents::record($user->isBackofficeAccount() ? 'login_password_valid_backoffice' : 'login_success', ['user_id' => $user->id]);
            return $this->successResponse(['user' => new UserResource($user), 'status' => $user->must_change_password ? 'PASSWORD_CHANGE_REQUIRED'
                : ($user->isBackofficeAccount() ? ($user->mfa_confirmed_at ? 'MFA_REQUIRED' : 'MFA_ENROLLMENT_REQUIRED') : 'AUTHENTICATED')], 'Credenciales verificadas.');
        });
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->registrarAuditoria->execute(accion: 'logout', modelo: 'User', modeloId: $user->id,
            descripcion: 'Cierre de sesión.', actorId: $user->id);
        if ($user->currentAccessToken() instanceof PersonalAccessToken) {
            $user->currentAccessToken()->delete();
        }
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return $this->successResponse(null, 'Sesión cerrada correctamente.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()->load('funcionario')), 'Identidad verificada.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $originalToken = $request->user()->currentAccessToken();
            if (! $user->estado || ($originalToken instanceof PersonalAccessToken && ! $originalToken->fresh())
                || (! $originalToken instanceof PersonalAccessToken && (! $request->hasSession() || $request->session()->get('auth_version') !== $user->auth_version))) {
                throw new \App\Exceptions\IdentityException('SESSION_REVOKED', 'Inicie sesión nuevamente.', 401);
            }
            $user->assertIdentity();
            if (! Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['La contraseña actual no es correcta.']]);
            }
            $wasForced = $user->must_change_password;
            $user->forceFill(['password' => $request->validated('password'), 'must_change_password' => false, 'password_changed_at' => now()])->save();
            $user->increment('auth_version');
            if ($originalToken instanceof PersonalAccessToken) {
                $originalToken->forceFill(['created_at' => now()])->save();
                $user->tokens()->where('id', '<>', $originalToken->id)->delete();
            } else {
                $user->tokens()->delete();
                $request->session()->put('auth_version', $user->auth_version);
                if ($request->session()->has('pending_auth')) {
                    $request->session()->put('pending_auth', ['user_id' => $user->id, 'version' => $user->auth_version, 'at' => time()]);
                }
                $request->session()->regenerate();
                $request->session()->regenerateToken();
                // Sanctum stores the current guard's password HMAC after this response.
                // Keep that guard current without authenticating a pending MFA principal.
                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->setUser($user);
                }
            }
            $this->registrarAuditoria->execute(accion: 'forced_password_changed', modelo: 'User', modeloId: $user->id,
                descripcion: 'El usuario cambió su contraseña.', metadata: ['must_change_password' => ['anterior' => $wasForced, 'nuevo' => false]], actorId: $user->id);
            SecurityEvents::record('password_changed', ['user_id' => $user->id]);
            return $this->successResponse(new UserResource($user->fresh()->load('funcionario')), 'Contraseña actualizada correctamente.');
        });
    }
}
