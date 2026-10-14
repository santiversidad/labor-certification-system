<?php
// Local CT only: keep configuration cached on the existing Linux storage volume,
// and rebuild it atomically when config/.env changes. No route/event/view cache.
if (getenv('APP_ENV') !== 'local' || getenv('CT_DEV_CONFIG_CACHE') !== 'true') {
    fwrite(STDERR, "CT development config cache requires APP_ENV=local and explicit opt-in.\n");
    exit(1);
}
$root = getenv('CT_DEV_CONFIG_ROOT') ?: '/var/www/html';
$target = getenv('APP_CONFIG_CACHE');
if (! $target || ! str_starts_with($target, '/') || ! is_dir(dirname($target))) {
    throw new RuntimeException('APP_CONFIG_CACHE must point to an existing writable Linux directory.');
}
function fingerprint(string $root): string {
    $files = [$root.'/.env', $root.'/composer.lock', $root.'/bootstrap/providers.php'];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/config', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') $files[] = $file->getPathname();
    }
    sort($files);
    $hash = hash_init('sha256');
    foreach ($files as $file) hash_update($hash, $file.(is_file($file) ? hash_file('sha256', $file) : 'missing'));
    return hash_final($hash);
}
function refresh(string $root, string $target): bool {
    $temporary = dirname($target).'/.ct-config-'.getmypid().'.php';
    $environment = getenv();
    $environment['APP_CONFIG_CACHE'] = $temporary;
    @unlink($temporary);
    $process = proc_open([PHP_BINARY, $root.'/artisan', 'config:cache', '--no-ansi', '--no-interaction'],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    if (! is_resource($process)) return false;
    // Do not log configuration values or framework errors that may contain secrets.
    stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $success = proc_close($process) === 0 && is_file($temporary);
    if ($success) {
        chmod($temporary, 0640);
        chown($temporary, 'www-data'); chgrp($temporary, 'www-data');
        $success = rename($temporary, $target);
    }
    @unlink($temporary);
    fwrite(STDERR, $success ? "CT development configuration refreshed.\n" : "CT development config refresh failed; last valid cache retained.\n");
    return $success;
}
$previous = fingerprint($root);
if (($argv[1] ?? '') === 'refresh') exit(refresh($root, $target) ? 0 : 1);
if (($argv[1] ?? '') !== 'watch') throw new RuntimeException('Use refresh or watch.');
while (true) {
    sleep(2);
    clearstatcache();
    try {
        $current = fingerprint($root);
        if ($current !== $previous || ! is_file($target)) {
            if (refresh($root, $target)) $previous = $current;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "CT development config watcher will retry.\n");
    }
}
