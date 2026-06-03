<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Permite que el frontend React (en otro puerto/dominio) consuma la API
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todas las rutas /api/* siempre responden JSON, nunca HTML de error
        $exceptions->shouldRenderJsonWhen(
            fn (\Illuminate\Http\Request $request) => $request->is('api/*')
        );

        // 401 Unauthenticated → JSON consistente
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            \Illuminate\Http\Request $request,
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. Inicie sesión para continuar.',
                ], 401);
            }
        });

        // 403 Unauthorized → JSON consistente
        $exceptions->render(function (
            \Illuminate\Auth\Access\AuthorizationException $e,
            \Illuminate\Http\Request $request,
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para realizar esta acción.',
                ], 403);
            }
        });

        // 404 Not Found → JSON consistente
        $exceptions->render(function (
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e,
            \Illuminate\Http\Request $request,
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El recurso solicitado no existe.',
                ], 404);
            }
        });
    })->create();
