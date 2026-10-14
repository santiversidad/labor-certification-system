<?php
// Reuse an existing, unexpired MFA-verified local session. Never create a principal,
// change auth_version, manufacture MFA proof, or write any database record.
$root = $argv[1] ?? '/var/www/html';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap();
if (! $app->environment('local') || config('session.driver') !== 'file') {
    throw new RuntimeException('Only existing local file sessions are supported.');
}
$role = $argv[2] ?? 'admin';
$files = glob(config('session.files').'/*');
usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
foreach ($files as $file) {
    if (filemtime($file) < time() - config('session.lifetime') * 60) continue;
    try {
        $raw = file_get_contents($file);
        if (config('session.encrypt')) $raw = app('encrypter')->decrypt($raw);
        $data = config('session.serialization') === 'json' ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR)
            : unserialize($raw, ['allowed_classes' => false]);
        $key = array_values(array_filter(array_keys($data), fn ($k) => str_starts_with($k, 'login_web_')))[0] ?? null;
        if (! $key || ! is_int($data[$key])) continue;
        $user = App\Models\User::find($data[$key]);
        if (! $user || ! $user->estado || $user->must_change_password || $user->auth_version !== ($data['auth_version'] ?? null)
            || ! $user->hasRole($role) || time() - ($data['auth_started_at'] ?? 0) > config('security.absolute_minutes') * 60) continue;
        $user->assertIdentity();
        if ($role !== 'funcionario' && (($data['mfa_verified_user'] ?? null) !== $user->id || ! $user->mfa_confirmed_at)) continue;
        $name = config('session.cookie');
        $value = app('encrypter')->encrypt(Illuminate\Cookie\CookieValuePrefix::create($name, app('encrypter')->getKey()).basename($file), false);
        $target = '/tmp/performance-'.$role.'.cookies';
        file_put_contents($target, "# Netscape HTTP Cookie File\nlocalhost\tFALSE\t/\tFALSE\t0\t".$name."\t".$value."\n");
        chmod($target, 0600);
        echo json_encode(['role' => $role, 'session_found' => true, 'cookie_file' => $target]).PHP_EOL;
        exit;
    } catch (Throwable $e) { continue; }
}
echo json_encode(['role' => $role, 'session_found' => false]).PHP_EOL;
exit(2);
