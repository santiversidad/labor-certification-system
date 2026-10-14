<?php

namespace App\Http\Middleware;

use App\Services\BrowserAuthentication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAuthenticationPrincipal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = app(BrowserAuthentication::class)->principal($request);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
