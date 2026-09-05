<?php

namespace App\Console\Commands;

use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualCargoVersion;
use App\Models\User;
use App\Services\ExpedirCertificacionService;
use App\Services\ImportarManualFuncionesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemostrarManualCommand extends Command
{
    protected $signature = 'manual:demo {--generar : Generar por el servicio de autoservicio}';

    protected $description = 'Crea tres funcionarios ficticios locales vinculados a fichas reales importadas';

    public function handle(ExpedirCertificacionService $expedir): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('MANUAL_DEMO_SOLO_DESARROLLO');

            return self::FAILURE;
        }
        $resultados = [];
        foreach (['MF-0001', 'MF-0228', 'MF-0229'] as $source) {
            $ficha = ManualCargoVersion::where('source_id', $source)
                ->whereHas('version', fn ($q) => $q->where('version', ImportarManualFuncionesService::VERSION)
                    ->whereHas('manual', fn ($m) => $m->where('codigo', ImportarManualFuncionesService::MANUAL)))
                ->sole();
            $documento = 'TEST-MANUAL-'.substr($source, 3);
            $user = DB::transaction(function () use ($ficha, $documento, $source) {
                $user = User::where('documento', $documento)->first();
                if ($user) {
                    $asignacion = $user->funcionario?->historialCargos()->where('es_prueba_manual', true)
                        ->where('manual_cargo_version_id', $ficha->id)->first();
                    if (! $asignacion) {
                        throw new \DomainException('MANUAL_DEMO_IDENTIDAD_EXISTENTE_INCOMPATIBLE');
                    }

                    return $user;
                }
                $password = Str::random(24);
                $user = User::create(['documento' => $documento, 'name' => 'Prueba Manual '.$source,
                    'password' => $password, 'estado' => true, 'must_change_password' => false,
                    'password_changed_at' => now(), 'email' => strtolower($documento).'@example.test']);
                $user->assignRole('funcionario');
                $funcionario = Funcionario::create(['user_id' => $user->id, 'tipo_documento' => 'CC',
                    'numero_documento' => $documento, 'nombres' => 'Prueba Manual', 'apellidos' => $source,
                    'estado' => 'activo', 'fecha_ingreso' => today(), 'cargo_id' => $ficha->cargo_id,
                    'dependencia' => $ficha->area_funcional]);
                FuncionarioCargo::create(['funcionario_id' => $funcionario->id, 'cargo_id' => $ficha->cargo_id,
                    'manual_cargo_version_id' => $ficha->id, 'es_prueba_manual' => true,
                    'tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa',
                    'es_cargo_base' => true, 'fecha_inicio' => today()]);
                // Local access details, never printed or committed. No real identity is used.
                Storage::disk('local')->put('manual-demo/acceso-'.$source.'.json', json_encode([
                    'documento' => $documento, 'password' => $password,
                ], JSON_PRETTY_PRINT));

                return $user;
            });
            $certificado = null;
            if ($this->option('generar')) {
                $certificado = Certificado::where('funcionario_id', $user->funcionario->id)
                    ->whereHas('solicitud', fn ($q) => $q->whereDate('periodo_mes', now()->startOfMonth())->where('requiere_salario', false))->first();
                if (! $certificado) {
                    $result = $expedir->expedir($user, false, 'funciones', 'Prueba de desarrollo con ficha importada.');
                    if ($result['estado'] !== 'generada') {
                        $this->error('MANUAL_DEMO_PAGO_PENDIENTE: no se altera la configuración de pago.');

                        return self::FAILURE;
                    }
                    $certificado = $result['certificado'];
                }
                Storage::disk('local')->put('manual-demo/'.$source.'.pdf', Storage::disk('local')->get($certificado->archivo_pdf_path));
                Storage::disk('local')->put('manual-demo/'.$source.'-snapshot.json', json_encode($certificado->snapshot_datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            $resultados[] = ['documento_ficticio' => $documento, 'funcionario_id' => $user->funcionario->id,
                'cargo_id' => $ficha->cargo_id, 'ficha_id' => $ficha->id, 'source_id' => $source,
                'area_funcional' => $ficha->area_funcional, 'funciones' => $ficha->funciones()->count(),
                'certificado_id' => $certificado?->id, 'pdf' => $certificado ? Storage::disk('local')->path('manual-demo/'.$source.'.pdf') : null];
        }
        $this->line(json_encode($resultados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
