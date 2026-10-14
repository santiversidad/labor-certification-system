<?php
// Run only against the diagnostic Linux copy, never against the CT bind mount.
$root = '/tmp/performance-linux';
$target = '/tmp/performance-test-config.php';
$source = $root.'/config/performance_refresh_probe.php';
$environment = getenv();
$environment['CT_DEV_CONFIG_CACHE'] = 'true';
$environment['CT_DEV_CONFIG_ROOT'] = $root;
$environment['APP_CONFIG_CACHE'] = $target;
$watcher = proc_open([PHP_BINARY, '/tmp/ct-dev-config.php', 'watch'],
    [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/tmp/performance-config-test.log','w']], $pipes, $root, $environment);
function awaitMarker($target, $expected): void {
    $deadline = microtime(true) + 12;
    while (microtime(true) < $deadline) {
        clearstatcache();
        $config = is_file($target) ? require $target : [];
        if (($config['performance_refresh_probe']['marker'] ?? null) === $expected) return;
        usleep(100000);
    }
    throw new RuntimeException('Development config watcher failed to refresh.');
}
try {
    // Allow watcher to capture its initial signature before the first change.
    usleep(300000);
    file_put_contents($source, '<?php return ["marker" => "first"];');
    awaitMarker($target, 'first');
    file_put_contents($source, '<?php return ["marker" => "changed"];');
    awaitMarker($target, 'changed');
    file_put_contents($source, '<?php this is deliberately invalid PHP;');
    sleep(3);
    awaitMarker($target, 'changed'); // Failed rebuild must preserve the last valid cache.
    unlink($source);
    awaitMarker($target, null);
    echo "PASS: addition, edit, invalid rebuild retention, deletion.\n";
} finally {
    proc_terminate($watcher);
    proc_close($watcher);
    if (is_file($source)) unlink($source);
}
