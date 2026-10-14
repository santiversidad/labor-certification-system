<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

if (getenv('APP_ENV') === 'local' && ($_SERVER['HTTP_X_PERFORMANCE_PROBE'] ?? '') === '1' && is_file('/tmp/http-probe.php')) {
    $GLOBALS['performance_entry_ms'] = (microtime(true) - LARAVEL_START) * 1000;
    (require '/tmp/http-probe.php')($app, Request::capture());
} else {
    $app->handleRequest(Request::capture());
}
