<?php

namespace App\Console\Commands;

use App\Models\ManualFuncion;
use App\Models\ManualFuncionVersion;
use App\Models\User;
use App\Services\ImportarManualFuncionesService;
use App\Services\PublicarVersionManualService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapManualDesarrolloCommand extends Command
{
    private const SOURCE_SHA256 = '261a8af4e5cc0f8aa1394dbcddfcaba7863a499f4ca98e30d20749e415bf356e';

    protected $signature = 'manual:bootstrap-development {--actor-id= : ID de un administrador sintético de CT}';

    protected $description = 'Carga el Manual validado en CT desarrollo usando el importador y publicador del dominio';

    public function handle(ImportarManualFuncionesService $importador, PublicarVersionManualService $publicador): int
    {
        try {
            if (! app()->environment('local') || DB::getDatabaseName() !== 'scl_certificate_types_dev') {
                throw new DomainException('MANUAL_BOOTSTRAP_SOLO_CT_DESARROLLO');
            }

            $actorId = filter_var($this->option('actor-id'), FILTER_VALIDATE_INT);
            if (! $actorId || ! User::whereKey($actorId)->first()?->hasRole('admin')) {
                throw new DomainException('MANUAL_BOOTSTRAP_ADMIN_CT_REQUERIDO');
            }

            $archivo = database_path('data/manual_funciones_decreto_015_2023.json');
            if (! is_file($archivo) || hash_file('sha256', $archivo) !== self::SOURCE_SHA256) {
                throw new DomainException('MANUAL_BOOTSTRAP_FUENTE_NO_VALIDADA');
            }

            $this->assertEmpty();

            // The official importer validates all source IDs and existing generic cargos without writing.
            $dryRun = $importador->ejecutar($archivo, true);
            if ($dryRun['errores'] || $dryRun['registros_encontrados'] !== 344
                || $dryRun['funciones'] !== 3115 || $dryRun['fichas_nuevas'] !== 344) {
                throw new DomainException('MANUAL_BOOTSTRAP_PREFLIGHT_FALLIDO: '.json_encode($dryRun['errores']));
            }

            DB::transaction(function () use ($archivo, $actorId, $importador, $publicador) {
                DB::select('SELECT pg_advisory_xact_lock(1502023)');
                $this->assertEmpty();

                $manual = ManualFuncion::create([
                    'codigo' => ImportarManualFuncionesService::MANUAL,
                    'nombre' => 'Manual de Funciones y Competencias Laborales - Villavicencio',
                ]);
                $version = new ManualFuncionVersion;
                $version->id = 10; // The validated baseline and adoption workflow identify this version as 10.
                $version->fill([
                    'manual_funciones_id' => $manual->id,
                    'version' => ImportarManualFuncionesService::VERSION,
                    'acto_tipo' => 'Decreto',
                    'acto_numero' => '1000-24/015',
                    'acto_referencia' => ImportarManualFuncionesService::FUENTE,
                    'estado' => 'borrador',
                    'metadata_manual' => [
                        'fecha_portada' => '2023-01-13',
                        'fecha_expedicion' => null,
                        'pendientes' => ['Confirmar expedición y vigencia', 'Conciliar Excel', 'Completar campos omitidos por JSON'],
                        'funciones_comunes_disponibles' => false,
                        'version_identificador' => 'Referencia del acto; no numeración documental inventada',
                    ],
                ]);
                $version->save();

                $import = $importador->ejecutar($archivo, false, $version->id);
                if ($import['errores'] || $import['fichas_nuevas'] !== 344 || $import['funciones'] !== 3115) {
                    throw new DomainException('MANUAL_BOOTSTRAP_IMPORTACION_FALLIDA: '.json_encode($import['errores']));
                }

                $preflight = $publicador->preflight($version);
                if ($preflight['fichas'] !== 344 || $preflight['funciones'] !== 3115
                    || $preflight['conocimientos'] !== 2326 || $preflight['incompletas'] !== ['MF-0167']) {
                    throw new DomainException('MANUAL_BOOTSTRAP_CONTEOS_O_INCOMPLETITUD_INESPERADOS');
                }

                $publicador->publicar($version, null, $actorId, true);
                // Explicit ID 10 must not collide with the next version created by the application.
                DB::select("SELECT setval(pg_get_serial_sequence('manual_funciones_versiones', 'id'), 10, true)");
            });

            $this->info('Manual CT publicado: versión 10; 344 fichas, 3.115 funciones, 2.326 conocimientos.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function assertEmpty(): void
    {
        foreach ([
            'manuales_funciones', 'manual_funciones_versiones', 'manual_cargo_versiones',
            'manual_funciones_esenciales', 'manual_funciones_comunes_nivel', 'manual_importaciones',
            'manual_cargo_lineages', 'manual_actualizaciones_asignaciones', 'funcionario_cargo_manual_fichas',
        ] as $table) {
            if (DB::table($table)->exists()) {
                throw new DomainException("MANUAL_BOOTSTRAP_DESTINO_NO_VACIO: {$table}");
            }
        }
        if (DB::table('funcionario_cargo')->whereNotNull('manual_cargo_version_id')->exists()) {
            throw new DomainException('MANUAL_BOOTSTRAP_ASIGNACIONES_PREEXISTENTES');
        }
    }
}
