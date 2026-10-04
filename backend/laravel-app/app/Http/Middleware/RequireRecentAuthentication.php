<?php

namespace App\Http\Middleware;

use App\Exceptions\IdentityException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || $request->session()->get('step_up_user') !== $request->user()->id
            || time() - (int) $request->session()->get('step_up_at', 0) > config('security.step_up_seconds')) {
            throw new IdentityException('REAUTH_REQUIRED', 'Confirme su contraseña y código de autenticación para esta acción.', 403);
        }

        return $next($request);
    }
}
