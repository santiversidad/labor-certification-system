<?php

namespace App\Http\Middleware;

use App\Exceptions\IdentityException;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequireMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->isBackofficeAccount()) {
            $token = $user->currentAccessToken();
            $verified = $token instanceof PersonalAccessToken
                ? $token->can('backoffice:mfa') && config('security.api_tokens_enabled')
                : $request->hasSession() && $request->session()->get('mfa_verified_user') === $user->id && $user->mfa_confirmed_at;
            if (! $verified) {
                throw new IdentityException('MFA_REQUIRED', 'Complete la autenticación en dos pasos.', 403);
            }
        }

        return $next($request);
    }
}
