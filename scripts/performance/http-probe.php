<?php
// Temporary local HTTP instrumentation. Installed by install-probe.php, never routed in production.
return function ($app, $request) {
    $trace = ['bootstrap' => [], 'middleware' => [], 'sql_ms' => 0, 'sql_count' => 0];
    $start = hrtime(true);
    foreach (['LoadEnvironmentVariables', 'LoadConfiguration', 'HandleExceptions', 'RegisterFacades', 'RegisterProviders', 'BootProviders'] as $name) {
        $class = 'Illuminate\\Foundation\\Bootstrap\\'.$name;
        $stageStart = null;
        $app->beforeBootstrapping($class, function () use (&$stageStart) { $stageStart = hrtime(true); });
        $app->afterBootstrapping($class, function () use (&$stageStart, &$trace, $name) { $trace['bootstrap'][$name] = (hrtime(true) - $stageStart) / 1e6; });
    }
    $wrapMiddleware = function ($name) use ($app, &$trace) {
        if (! class_exists($name)) return;
        $app->extend($name, function ($middleware) use ($name, &$trace) {
            return new class($middleware, $name, $trace) {
                private $trace;
                public function __construct(private $inner, private $name, &$trace) { $this->trace =& $trace; }
                public function handle($request, $next, ...$parameters) {
                    $start = hrtime(true); $child = 0;
                    $wrapped = function ($request) use ($next, &$child) {
                        $start = hrtime(true);
                        try { return $next($request); } finally { $child += hrtime(true) - $start; }
                    };
                    try { return $this->inner->handle($request, $wrapped, ...$parameters); }
                    finally { $this->trace['middleware'][] = ['name' => $this->name, 'exclusive_ms' => (hrtime(true) - $start - $child) / 1e6]; }
                }
                public function terminate($request, $response) { if (method_exists($this->inner, 'terminate')) $this->inner->terminate($request, $response); }
            };
        });
    };
    $app->booted(function () use ($app, &$trace, $wrapMiddleware) {
        Illuminate\Support\Facades\DB::listen(function ($query) use (&$trace) { $trace['sql_ms'] += $query->time; $trace['sql_count']++; });
        // Temporary routes include the normal global and API middleware and return no secrets.
        Illuminate\Support\Facades\Route::middleware('api')->get('api/_diagnostics/empty', fn () => response()->json(['ok' => true]));
        Illuminate\Support\Facades\Route::middleware('api')->get('api/_diagnostics/db', function () use (&$trace) {
            $start = hrtime(true);
            Illuminate\Support\Facades\DB::connection()->getPdo();
            $trace['db_connect_ms'] = (hrtime(true) - $start) / 1e6;
            Illuminate\Support\Facades\DB::select('SELECT 1');
            return response()->json(['ok' => true]);
        });
        $classes = $app->make(Illuminate\Contracts\Http\Kernel::class)->getGlobalMiddleware();
        foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $classes = array_merge($classes, app('router')->resolveMiddleware($route->middleware()));
        }
        $classes = array_merge($classes, [Illuminate\Cookie\Middleware\EncryptCookies::class, Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            Illuminate\Session\Middleware\StartSession::class, Laravel\Sanctum\Http\Middleware\ValidateCsrfToken::class,
            Illuminate\Session\Middleware\AuthenticateSession::class]);
        foreach (array_unique(array_map(fn ($name) => explode(':', $name)[0], $classes)) as $name) $wrapMiddleware($name);
        $app->extend(Illuminate\Routing\Contracts\ControllerDispatcher::class, function ($inner) use (&$trace) {
            return new class($inner, $trace) implements Illuminate\Routing\Contracts\ControllerDispatcher {
                private $trace;
                public function __construct(private $inner, &$trace) { $this->trace =& $trace; }
                public function dispatch(Illuminate\Routing\Route $route, $controller, $method) {
                    $start = hrtime(true);
                    try { return $this->inner->dispatch($route, $controller, $method); }
                    finally { $this->trace['controller_including_json_ms'] = (hrtime(true) - $start) / 1e6; }
                }
                public function getMiddleware($controller, $method) { return $this->inner->getMiddleware($controller, $method); }
            };
        });
        $app['events']->listen(Illuminate\Routing\Events\PreparingResponse::class, function () use (&$trace) { $trace['prepare_start'] = hrtime(true); });
        $app['events']->listen(Illuminate\Routing\Events\ResponsePrepared::class, function () use (&$trace) {
            $trace['response_prepare_ms'] = ($trace['response_prepare_ms'] ?? 0) + (hrtime(true) - $trace['prepare_start']) / 1e6;
            unset($trace['prepare_start']);
        });
    });
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $trace['kernel_ms'] = (hrtime(true) - $start) / 1e6;
    $trace['php_to_response_ms'] = (microtime(true) - LARAVEL_START) * 1000;
    $trace['entry_ms'] = $GLOBALS['performance_entry_ms'] ?? null;
    $trace['route'] = $request->route()?->uri();
    $trace['status'] = $response->getStatusCode();
    $trace['opcache_cached_scripts'] = opcache_get_status(false)['opcache_statistics']['num_cached_scripts'] ?? null;
    $response->headers->set('Server-Timing', 'php;dur='.round($trace['php_to_response_ms'], 3).', bootstrap;dur='.round(array_sum($trace['bootstrap']), 3).', sql;dur='.round($trace['sql_ms'], 3));
    $response->send();
    $kernel->terminate($request, $response);
    file_put_contents('/tmp/performance-traces.jsonl', json_encode($trace).PHP_EOL, FILE_APPEND | LOCK_EX);
};
