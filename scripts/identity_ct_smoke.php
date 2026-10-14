<?php

// Optional synthetic HTTP smoke. Credentials/tokens remain in memory only.
use App\Models\AuditLog;
use App\Models\ParametroSistema;
use App\Models\User;
use App\Services\BackofficeIdentityService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'scl_certificate_types_dev') {
    throw new RuntimeException('CT_SMOKE_ISOLATION_REQUIRED');
}
$service = app(BackofficeIdentityService::class);
$prefix = 'p28ct.'.bin2hex(random_bytes(4));
$ids = [];
$tokens = [];
$previousPayment = ParametroSistema::valor('requiere_pago_certificado', false);
$call = function (string $method, string $path, array $data = [], ?string $token = null, int $status = 200) {
    $request = Http::acceptJson();
    if ($token) {
        $request = $request->withToken($token);
    }
    $response = match ($method) {
        'get' => $request->get('http://nginx/api/v1/'.$path, $data),
        'patch' => $request->patch('http://nginx/api/v1/'.$path, $data),
        'put' => $request->put('http://nginx/api/v1/'.$path, $data),
        default => $request->post('http://nginx/api/v1/'.$path, $data),
    };
    if ($response->status() !== $status) {
        throw new RuntimeException('SMOKE_FAIL: '.$path.' status '.$response->status().' code '.($response->json('code') ?? 'none'));
    }

    return $response->json('data');
};
try {
    // Explicitly chosen usernames for four already inventoried synthetic legacy actors.
    foreach ([1 => ['admin_test.ct28', 'admin'], 2 => ['secretario_test.ct28', 'secretario'],
        9 => ['p27ct.admin_test', 'admin'], 10 => ['p27ct.secretario_test', 'secretario']] as $id => [$username, $role]) {
        $legacy = User::findOrFail($id);
        if ($legacy->tipo_cuenta === 'legacy') {
            $service->provisionLegacy($legacy, $username, $role); // discard the random credential
        }
    }
    $first = $service->create(['name' => 'Admin Backoffice A ficticio CT28', 'username' => $prefix.'.a', 'role' => 'admin']);
    $a = $first['user'];
    $ids['admin_a'] = $a->id;
    $session = $call('post', 'auth/login', ['username' => $a->username, 'password' => $first['temporary_password']]);
    $tokens[] = $adminToken = $session['token'];
    $call('get', 'admin/usuarios-internos', [], $adminToken, 403);
    $call('put', 'auth/change-password', ['current_password' => $first['temporary_password'], 'password' => 'AdminSyntheticClave2026', 'password_confirmation' => 'AdminSyntheticClave2026'], $adminToken);
    unset($first);
    foreach (['b' => 'admin', 'secretary' => 'secretario', 'dual' => 'admin'] as $label => $role) {
        $result = $call('post', 'admin/usuarios-internos', ['name' => 'Persona ficticia CT28 '.$label, 'username' => $prefix.'.'.$label, 'role' => $role], $adminToken, 201);
        $ids[$label] = $result['user']['id'];
        $session = $call('post', 'auth/login', ['username' => $result['user']['username'], 'password' => $result['temporary_password']]);
        $tokens[] = $token = $session['token'];
        $call('put', 'auth/change-password', ['current_password' => $result['temporary_password'], 'password' => 'BackofficeSyntheticClave2026', 'password_confirmation' => 'BackofficeSyntheticClave2026'], $token);
        $call('get', 'mi-certificacion/disponibilidad', [], $token, 403);
        if ($role === 'secretario') {
            $secretaryToken = $token;
            $call('get', 'secretaria/pagos', [], $token);
            $call('get', 'admin/usuarios-internos', [], $token, 403);
        }
        unset($result);
    }
    $call('post', 'admin/usuarios-internos/'.$ids['secretary'].'/desactivar', ['motivo' => 'Fin de cargo ficticio CT28'], $adminToken);
    $call('get', 'auth/me', [], $secretaryToken, 401);
    $reactivated = $call('post', 'admin/usuarios-internos/'.$ids['secretary'].'/reactivar', [], $adminToken);
    $call('get', 'auth/me', [], $secretaryToken, 401);
    $new = $call('post', 'auth/login', ['username' => $reactivated['user']['username'], 'password' => $reactivated['temporary_password']]);
    $tokens[] = $new['token'];
    $reset = $call('post', 'admin/usuarios-internos/'.$ids['secretary'].'/reset-password', [], $adminToken);
    $call('get', 'auth/me', [], $new['token'], 401);
    $call('post', 'admin/usuarios-internos/'.$ids['b'].'/cambiar-rol', ['role' => 'secretario'], $adminToken);
    $call('post', 'admin/usuarios-internos/'.$ids['admin_a'].'/desactivar', ['motivo' => 'Auto prueba CT28'], $adminToken, 409);

    $cargo = DB::table('manual_cargo_versiones')->where('manual_funciones_version_id', 10)->where('source_id', 'MF-0002')->first();
    $call('patch', 'configuracion/certificaciones', ['requiere_pago_certificado' => false], $adminToken);
    foreach (['independent', 'dual'] as $i => $label) {
        $document = '9828'.substr((string) time(), -8).$i;
        $employee = $call('post', 'funcionarios', ['tipo_documento' => 'CC', 'numero_documento' => $document,
            'nombres' => 'Persona ficticia', 'apellidos' => 'CT28 '.$label, 'cargo_id' => $cargo->cargo_id,
            'manual_cargo_version_id' => $cargo->id, 'estado' => 'activo', 'fecha_ingreso' => '2024-01-15',
            'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa'], $adminToken, 201);
        $ids['employee_'.$label] = $employee['id'];
        $ids['employee_user_'.$label] = $employee['usuario']['id'];
        $session = $call('post', 'auth/login', ['username' => $document, 'password' => $document]);
        $tokens[] = $token = $session['token'];
        $call('put', 'auth/change-password', ['current_password' => $document, 'password' => 'EmployeeSyntheticClave2026', 'password_confirmation' => 'EmployeeSyntheticClave2026'], $token);
        $call('get', 'mi-certificacion/disponibilidad', [], $token);
        $call('get', 'admin/usuarios-internos', [], $token, 403);
        $certificate = $call('post', 'solicitudes', ['tipo_certificado' => 'sencillo'], $token, 201);
        if ($certificate['resultado'] !== 'generada') {
            throw new RuntimeException('CT_CERTIFICATE_FAILED');
        }
        $ids['certificate_'.$label] = $certificate['certificado']['id'];
    }
    echo json_encode(['result' => 'PASS', 'synthetic_ids' => $ids, 'audit_events' => AuditLog::where('modelo', 'User')->whereIn('modelo_id', array_values($ids))->pluck('accion')->unique()->values()], JSON_PRETTY_PRINT), PHP_EOL;
} finally {
    ParametroSistema::where('clave', 'requiere_pago_certificado')->update(['valor' => $previousPayment ? 'true' : 'false']);
    foreach ($ids as $label => $id) {
        if (str_starts_with($label, 'employee_user_') || in_array($label, ['admin_a', 'b', 'secretary', 'dual'], true)) {
            User::find($id)?->revokeAuthentication();
        }
    }
    unset($tokens, $adminToken, $secretaryToken, $reactivated, $reset);
}
