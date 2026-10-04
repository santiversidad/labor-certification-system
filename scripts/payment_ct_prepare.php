<?php

use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualCargoVersion;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Explicit, optional synthetic smoke fixtures; never invoked by seeders or installation.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'scl_certificate_types_dev') {
    throw new RuntimeException('CT_SMOKE_ISOLATION_REQUIRED');
}
$result = DB::transaction(function () {
    $suffix = bin2hex(random_bytes(4));
    $result = ['users' => [], 'employees' => []];
    foreach (['admin' => 'admin', 'secretaria' => 'secretario', 'off' => 'funcionario', 'on' => 'funcionario', 'incomplete' => 'funcionario'] as $label => $role) {
        $user = User::create(['documento' => 'P27CT-'.$suffix.'-'.substr($label, 0, 3), 'name' => 'Prueba ficticia CT pago '.$label,
            'password' => Hash::make(bin2hex(random_bytes(24))), 'estado' => true, 'must_change_password' => false]);
        $user->assignRole($role);
        $result['users'][$label] = ['id' => $user->id, 'token' => $user->createToken('phase27-ct-smoke')->plainTextToken];
        if ($role === 'funcionario') {
            $source = $label === 'incomplete' ? 'MF-0167' : 'MF-0002';
            $ficha = ManualCargoVersion::where('source_id', $source)->where('manual_funciones_version_id', 10)->firstOrFail();
            $funcionario = Funcionario::create(['user_id' => $user->id, 'numero_documento' => $user->documento,
                'tipo_documento' => 'CC', 'nombres' => 'Prueba ficticia pago', 'apellidos' => 'CT '.$label,
                'estado' => 'activo', 'cargo_id' => $ficha->cargo_id, 'fecha_ingreso' => '2024-01-15', 'dependencia' => 'Pruebas CT']);
            $asignacion = FuncionarioCargo::create(['funcionario_id' => $funcionario->id, 'cargo_id' => $ficha->cargo_id,
                'manual_cargo_version_id' => $ficha->id, 'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa',
                'fecha_inicio' => '2024-01-15', 'es_cargo_base' => true]);
            $asignacion->fichasNormativas()->create(['manual_cargo_version_id' => $ficha->id, 'vigencia_desde' => '2024-01-15', 'origen' => 'smoke_ficticio_phase27']);
            $result['employees'][$label] = ['id' => $funcionario->id, 'source_id' => $source];
        }
    }

    return $result;
});
file_put_contents('/workspace-output/payment-phase27-session.json', json_encode($result, JSON_PRETTY_PRINT));
echo "Fixtures sintéticos CT preparados; credenciales temporales guardadas únicamente en output ignorado.\n";
