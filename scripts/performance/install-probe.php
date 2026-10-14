<?php
// docker cp both PHP files to /tmp, then: php /tmp/install-probe.php install|remove
$file = '/var/www/html/public/index.php';
$backup = '/tmp/performance-index-original.php';
if (($argv[1] ?? '') === 'remove') {
    if (is_file($backup)) { file_put_contents($file, file_get_contents($backup)); unlink($backup); }
    exit;
}
if (getenv('APP_ENV') !== 'local') throw new RuntimeException('HTTP probe is local-only.');
if (is_file($backup)) throw new RuntimeException('Probe already installed. Remove first.');
$source = file_get_contents($file);
file_put_contents($backup, $source);
$replacement = <<<'PHP'
if (getenv('APP_ENV') === 'local' && ($_SERVER['HTTP_X_PERFORMANCE_PROBE'] ?? '') === '1' && is_file('/tmp/http-probe.php')) {
    $GLOBALS['performance_entry_ms'] = (microtime(true) - LARAVEL_START) * 1000;
    (require '/tmp/http-probe.php')($app, Request::capture());
} else {
    $app->handleRequest(Request::capture());
}
PHP;
if (substr_count($source, '$app->handleRequest(Request::capture());') !== 1) throw new RuntimeException('Unexpected index.php; no changes applied.');
file_put_contents($file, str_replace('$app->handleRequest(Request::capture());', $replacement, $source));
