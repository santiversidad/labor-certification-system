<?php

namespace App\Http\Middleware;

use App\Exceptions\IdentityException;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $user->refresh()->unsetRelation('roles')->unsetRelation('funcionario');
        if (! $user->estado) {
            throw new IdentityException('BACKOFFICE_USER_INACTIVE', 'Su cuenta está inactiva. Contacte al administrador.', 401);
        }
        // Bearer revocation is persisted by deleting tokens. File/cookie sessions fail closed
        // unless authentication recorded the current epoch. Old sessions never revive.
        $token = $user->currentAccessToken();
        if ($token instanceof TransientToken
            && (! $request->hasSession() || $request->session()->get('auth_version') !== $user->auth_version)) {
            throw new IdentityException('SESSION_REVOKED', 'La sesión fue revocada. Inicie sesión nuevamente.', 401);
        }
        if ($request->hasSession() && $request->session()->has('auth_started_at')
            && time() - (int) $request->session()->get('auth_started_at') > config('security.absolute_minutes') * 60) {
            app(\App\Services\BrowserAuthentication::class)->expire($request);
        }
        if ($user->isFuncionarioAccount() && $user->funcionario?->estado !== \App\Enums\EstadoFuncionarioEnum::Activo) {
            throw new IdentityException('SESSION_REVOKED', 'Inicie sesión nuevamente.', 401);
        }
        $user->assertIdentity();

        return $next($request);
    }
}
