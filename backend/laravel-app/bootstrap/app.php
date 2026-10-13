<?php

use App\Exceptions\CertificateTemplateException;
use App\Exceptions\IdentityException;
use App\Http\Middleware\BrowserBoundary;
use App\Http\Middleware\EnforceCsrfToken;
use App\Http\Middleware\EnsureCurrentIdentity;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\RequireFuncionarioContext;
use App\Http\Middleware\RequireMfa;
use App\Http\Middleware\RequireRecentAuthentication;
use App\Http\Middleware\ResolveAuthenticationPrincipal;
use App\Http\Middleware\SecurityHeaders;
use App\Services\SecurityEvents;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        // Spaces between literal text and variable tokens are document content.
        $middleware->trimStrings(except: ['contenido.paragraphs.*.runs.*.text']);
        $middleware->redirectGuestsTo(null);
        $middleware->statefulApi();
        $middleware->web(replace: [PreventRequestForgery::class => EnforceCsrfToken::class]);
        $middleware->prepend(BrowserBoundary::class);
        $middleware->prepend(SecurityHeaders::class);
        $middleware->trustProxies(at: array_filter(explode(',', env('TRUSTED_PROXIES', ''))));
        $middleware->alias([
            'auth.principal' => ResolveAuthenticationPrincipal::class,
            'mfa' => RequireMfa::class,
            'auth.recent' => RequireRecentAuthentication::class,
            'password.changed' => EnsurePasswordChanged::class,
            'identity.current' => EnsureCurrentIdentity::class,
            'context.funcionario' => RequireFuncionarioContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'code', 'recovery_code', 'mfa_secret']);
        $exceptions->report(function (Throwable $e) {
            // Framework exception messages may embed SQL bindings or request secrets.
            Log::error('Application exception', ['type' => $e::class, 'code' => (string) $e->getCode()]);

            return false;
        });
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            SecurityEvents::record('csrf_rejected');

            return response()->json(['success' => false, 'code' => 'CSRF_REJECTED', 'message' => 'La sesión de seguridad expiró. Actualice la página.'], 419);
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*', 'login', 'logout', 'sanctum/*'));

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'No autenticado. Inicie sesión para continuar.'], 401);
            }
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                SecurityEvents::record('forbidden_access', ['user_id' => $request->user()?->id]);

                return response()->json(['success' => false, 'message' => 'No tiene permisos para realizar esta acción.'], 403);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'El recurso solicitado no existe.'], 404);
            }
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los datos suministrados no son válidos.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
        $exceptions->render(function (IdentityException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'code' => $e->domainCode], $e->status);
            }
        });
        $exceptions->dontReport([IdentityException::class]);
        $exceptions->render(function (CertificateTemplateException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'code' => $e->domainCode, 'message' => $e->getMessage()], $e->status);
            }
        });
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'La operación no cumple las reglas de versionado del Manual.',
                    'code' => preg_match('/^[A-Z_]{3,80}$/', $e->getMessage()) ? $e->getMessage() : 'DOMAIN_CONFLICT',
                ], 409);
            }
        });
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ha realizado demasiadas solicitudes. Intente nuevamente más tarde.',
                ], 429, $e->getHeaders());
            }
        });
        $exceptions->render(function (HttpResponseException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->getResponse();
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

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

                return response()->json(['success' => false, 'message' => $message], $status, $e->getHeaders());
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error interno. Intente nuevamente más tarde.',
            ], 500);
        });
    })->create();
