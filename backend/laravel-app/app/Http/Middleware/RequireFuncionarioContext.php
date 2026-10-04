<?php

namespace App\Http\Middleware;

use App\Exceptions\IdentityException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFuncionarioContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->isFuncionarioAccount()) {
            throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'Use su cuenta de funcionario para acceder a sus certificaciones.', 403);
        }

        return $next($request);
    }
}
