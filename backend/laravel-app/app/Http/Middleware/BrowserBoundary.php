<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

class BrowserBoundary
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && app(\App\Services\SecurityConfiguration::class)->critical() !== []) {
            \App\Services\SecurityEvents::record('security_configuration_failure', ['reason' => 'critical_config']);
            return response()->json(['success' => false, 'code' => 'SECURITY_CONFIGURATION_INVALID', 'message' => 'Servicio temporalmente no disponible.'], 503);
        }
        if ($request->is('api/*', 'login', 'logout', 'sanctum/csrf-cookie')) {
            $origin = $request->header('Origin');
            if ($origin !== null && ! in_array($origin, config('cors.allowed_origins'), true)) {
                abort(403, 'ORIGIN_NOT_ALLOWED');
            }
            if ($request->bearerToken() && (EnsureFrontendRequestsAreStateful::fromFrontend($request)
                || $request->hasHeader('Origin') || $request->hasHeader('Sec-Fetch-Mode')
                || ! config('security.api_tokens_enabled'))) {
                abort(401);
            }
            if ($request->isJson() && strlen($request->getContent()) > config('security.json_max_bytes')) {
                abort(413);
            }
        }

        return $next($request);
    }
}
