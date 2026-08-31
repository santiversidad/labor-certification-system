<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Las rutas API no tienen una pantalla web de login a la cual redirigir.
        $middleware->redirectGuestsTo(null);

        // Permite que el frontend React (en otro puerto/dominio) consuma la API
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todas las rutas /api/* siempre responden JSON, nunca HTML de error
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        // 401 Unauthenticated → JSON consistente
        $exceptions->render(function (
            AuthenticationException $e,
            Request $request,
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
            AuthorizationException $e,
            Request $request,
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
            NotFoundHttpException $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El recurso solicitado no existe.',
                ], 404);
            }
        });

        $exceptions->render(function (
            ValidationException $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los datos suministrados no son válidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (
            ThrottleRequestsException $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ha realizado demasiadas solicitudes. Intente nuevamente más tarde.',
                ], 429, $e->getHeaders());
            }
        });

        $exceptions->render(function (
            HttpResponseException $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                return $e->getResponse();
            }
        });

        $exceptions->render(function (
            Throwable $e,
            Request $request,
        ) {
            if ($request->is('api/*')) {
                if ($e instanceof HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    $message = match ($status) {
                        401 => 'No autenticado. Inicie sesión para continuar.',
                        403 => 'No tiene permisos para realizar esta acción.',
                        404 => 'El recurso solicitado no existe.',
                        409 => 'La operación entra en conflicto con el estado actual del recurso.',
                        422 => 'Los datos suministrados no son válidos.',
                        429 => 'Ha realizado demasiadas solicitudes. Intente nuevamente más tarde.',
                        default => $status >= 500
                            ? 'Ocurrió un error interno. Intente nuevamente más tarde.'
                            : 'No fue posible procesar la solicitud.',
                    };

                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], $status, $e->getHeaders());
                }

                report($e);

                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error interno. Intente nuevamente más tarde.',
                ], 500);
            }
        });
    })->create();
