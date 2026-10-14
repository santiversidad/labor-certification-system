<?php
// CLI, read-only: php /tmp/profile-runtime.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
$times = [];
$measure = function ($name, $fn) use (&$times) {
    $start = hrtime(true);
    $result = $fn();
    $times[$name] = (hrtime(true) - $start) / 1e6;
    return $result;
};
$measure('composer_autoload_ms', fn () => require $root.'/vendor/autoload.php');
$app = $measure('application_create_ms', fn () => require $root.'/bootstrap/app.php');
foreach (['LoadEnvironmentVariables', 'LoadConfiguration', 'HandleExceptions', 'RegisterFacades', 'RegisterProviders', 'BootProviders'] as $name) {
    $class = 'Illuminate\\Foundation\\Bootstrap\\'.$name;
    $start = null;
    $app->beforeBootstrapping($class, function () use (&$start) { $start = hrtime(true); });
    $app->afterBootstrapping($class, function () use (&$start, &$times, $name) { $times[$name.'_ms'] = (hrtime(true) - $start) / 1e6; });
}
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$measure('bootstrap_total_ms', fn () => $kernel->bootstrap());
$config = config('database.connections.pgsql');
for ($i = 1; $i <= 10; $i++) {
    $measure('dns_'.$i.'_ms', fn () => gethostbyname($config['host']));
    Illuminate\Support\Facades\DB::purge();
    $measure('db_connect_'.$i.'_ms', fn () => Illuminate\Support\Facades\DB::connection()->getPdo());
    $measure('select1_first_'.$i.'_ms', fn () => Illuminate\Support\Facades\DB::select('SELECT 1'));
    $measure('select1_reuse_'.$i.'_ms', fn () => Illuminate\Support\Facades\DB::select('SELECT 1'));
    $measure('small_query_'.$i.'_ms', fn () => Illuminate\Support\Facades\DB::select('SELECT id FROM users ORDER BY id LIMIT 10'));
}
echo json_encode(['times' => $times, 'runtime' => [
    'env' => $app->environment(), 'debug' => config('app.debug'), 'db_host' => $config['host'], 'db_database' => $config['database'],
    'session' => config('session.driver'), 'cache' => config('cache.default'),
    'config_cached' => $app->configurationIsCached(), 'routes_cached' => $app->routesAreCached(), 'events_cached' => $app->eventsAreCached(),
    'opcache' => ini_get('opcache.enable'), 'opcache_cli' => ini_get('opcache.enable_cli'), 'xdebug' => extension_loaded('xdebug'),
    'autoload_optimized' => count(require $root.'/vendor/composer/autoload_classmap.php') > 1000,
]], JSON_PRETTY_PRINT).PHP_EOL;
