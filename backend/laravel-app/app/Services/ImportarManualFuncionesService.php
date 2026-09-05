<?php

namespace App\Services;

use App\Domain\Manual\FichaManualData;
use App\Domain\Manual\JsonManualParser;
use App\Models\Cargo;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\ManualFuncionVersion;
use DomainException;
use Illuminate\Support\Facades\DB;

class ImportarManualFuncionesService
{
    public const MANUAL = 'DECRETO-1000-24-015-2023';

    public const VERSION = 'Decreto 1000-24/015 de 2023';

    public const FUENTE = 'Decreto No. 1000-24/015 de 2023 - Manual de Funciones y Competencias Laborales';

    public function __construct(private readonly JsonManualParser $parser) {}

    public function ejecutar(string $archivo, bool $dryRun = false, ?int $versionId = null, ?int $userId = null): array
    {
        if (! is_file($archivo) || ! is_readable($archivo)) {
            throw new DomainException('MANUAL_ARCHIVO_NO_LEIBLE');
        }
        // Read exactly once: parsing and audit hash refer to the same bytes even if source changes.
        $bytes = file_get_contents($archivo);
        $sha = hash('sha256', $bytes);
        $summary = ['archivo' => $archivo, 'sha256' => $sha, 'dry_run' => $dryRun,
            'registros_encontrados' => 0, 'funciones' => 0, 'cargos_encontrados' => 0, 'cargos_nuevos' => 0,
            'fichas_nuevas' => 0, 'fichas_actualizadas' => 0, 'fichas_sin_cambios' => 0,
            'fichas_sin_coincidencia' => 0, 'duplicados' => [], 'errores' => [], 'advertencias' => [],
            'source_ids' => ['nuevas' => [], 'actualizadas' => [], 'sin_cambios' => []], 'functions_diff' => []];
        $version = null;
        try {
            $records = $this->parser->parse($bytes);
            $summary['registros_encontrados'] = count($records);
            if (isset($records[0]->trazabilidad['conciliacion'])) {
                $summary['conciliacion'] = $records[0]->trazabilidad['conciliacion'];
                $summary['advertencias'][] = 'MANUAL_PROPUESTA_NO_APLICADA: conciliación pendiente de revisión explícita.';
                if (! $dryRun) {
                    $summary['errores'][] = 'MANUAL_RECONCILIACION_SOLO_DRY_RUN';
                }
            }
            $operation = function () use ($records, $versionId, $userId, $dryRun, &$version, &$summary) {
                if (! $dryRun) {
                    DB::select('SELECT pg_advisory_xact_lock(1502023)');
                }
                $version = $versionId ? ManualFuncionVersion::findOrFail($versionId)
                    : ManualFuncionVersion::whereHas('manual', fn ($q) => $q->where('codigo', self::MANUAL))
                        ->where('version', self::VERSION)->first();
                if ($version && ! $dryRun) {
                    $version = ManualFuncionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
                }
                if ($version && ($version->manual->codigo !== self::MANUAL || $version->acto_numero !== '1000-24/015')) {
                    throw new DomainException('MANUAL_VERSION_FUENTE_INCOMPATIBLE');
                }
                $existing = $version ? $version->cargos()->with('funciones')->get()->keyBy('source_id') : collect();
                $seen = [];
                $cargos = [];
                $naturals = [];
                $changes = [];
                foreach ($records as $dto) {
                    $r = $dto->origen;
                    $id = $r['perfil_id'];
                    if ($r['fuente'] !== self::FUENTE) {
                        $summary['errores'][] = "MANUAL_FUENTE_INCOMPATIBLE: {$id}";
                    }
                    if (isset($seen[$id])) {
                        $summary['duplicados'][] = $id;
                        $summary['errores'][] = "MANUAL_SOURCE_ID_DUPLICADO: {$id}";
                    }
                    $seen[$id] = true;
                    $naturals[$dto->natural()][] = $id;
                    $summary['funciones'] += count($dto->funciones());
                    foreach (['area_funcional', 'proposito_principal'] as $field) {
                        if (trim($r[$field]) === '') {
                            $summary['advertencias'][] = "MANUAL_FICHA_INCOMPLETA: {$id}.{$field}; no certificable.";
                        }
                    }
                    $attributes = $dto->cargo();
                    $key = $attributes['codigo'].'/'.$attributes['grado'];
                    if (isset($cargos[$key]) && $cargos[$key]['attributes'] !== $attributes) {
                        $summary['errores'][] = "MANUAL_CARGO_AMBIGUO: {$key}";
                    }
                    if (! isset($cargos[$key])) {
                        $cargo = Cargo::where('codigo', $attributes['codigo'])->where('grado', $attributes['grado'])->first();
                        if ($cargo && (FichaManualData::denominacionGenerica($cargo->denominacion, $cargo->codigo, $cargo->grado) !== $attributes['denominacion']
                            || ($cargo->nivel !== null && $cargo->nivel !== $attributes['nivel']))) {
                            $summary['errores'][] = "MANUAL_CARGO_EXISTENTE_INCOMPATIBLE: {$key}";
                        }
                        $cargos[$key] = ['model' => $cargo, 'attributes' => $attributes];
                        $summary[$cargo ? 'cargos_encontrados' : 'cargos_nuevos']++;
                    }
                    $ficha = $existing->get($id);
                    if (! $ficha) {
                        $summary['fichas_nuevas']++;
                        $summary['fichas_sin_coincidencia']++;
                        $summary['source_ids']['nuevas'][] = $id;
                        $summary['functions_diff'][] = ['source_id' => $id, 'antes' => [], 'despues' => $dto->funciones()];
                        $changes[$id] = true;
                    } else {
                        $same = $this->igual($ficha, $dto);
                        $summary[$same ? 'fichas_sin_cambios' : 'fichas_actualizadas']++;
                        $summary['source_ids'][$same ? 'sin_cambios' : 'actualizadas'][] = $id;
                        $beforeFunctions = $ficha->funciones->map(fn ($f) => $f->only(['orden', 'descripcion', 'numero_fuente', 'grupo']))->all();
                        if ($beforeFunctions !== $dto->funciones()) {
                            $summary['functions_diff'][] = ['source_id' => $id, 'antes' => $beforeFunctions, 'despues' => $dto->funciones()];
                        }
                        $changes[$id] = ! $same;
                        if ($ficha->natural_key !== $dto->natural()
                            || ($ficha->metadata_manual['registro_decreto'] ?? null) !== $r['registro_decreto']
                            || ($ficha->metadata_manual['pagina_inicio'] ?? null) !== $r['pagina_inicio']) {
                            $summary['errores'][] = "MANUAL_SOURCE_ID_REASIGNADO: {$id}; revise identidad o cree otra versión.";
                        }
                        if ($ficha->funciones->count() > count($dto->funciones())) {
                            $summary['errores'][] = "MANUAL_FUNCIONES_RETIRADAS: {$id}; requiere nueva versión, no se borran funciones.";
                        }
                    }
                }
                foreach ($existing as $id => $ficha) {
                    if (! isset($seen[$id])) {
                        $summary['errores'][] = "MANUAL_FICHA_AUSENTE_EN_FUENTE: {$id}; requiere conciliación, no se borra.";
                    }
                }
                foreach ($naturals as $ids) {
                    if (count($ids) > 1) {
                        $summary['advertencias'][] = 'IDENTIDAD_DESCRIPTIVA_REPETIDA: '.implode(', ', $ids).'; se distinguen por source_id.';
                    }
                }
                $summary['advertencias'][] = 'Fuente estructurada sin competencias, formación, experiencia, equivalencias ni funciones comunes. Consultar informe de conciliación; no publicar automáticamente.';
                if ($version && $version->estado !== 'borrador' && in_array(true, $changes, true)) {
                    $summary['errores'][] = 'MANUAL_VERSION_IMMUTABLE';
                }
                $summary['version_id'] = $version?->id;
                $summary['version'] = $version?->version ?? self::VERSION;
                $summary['resultado'] = $summary['errores'] ? 'rechazado' : ($dryRun ? 'dry_run' : 'importado');
                if ($summary['errores'] || $dryRun) {
                    return;
                }
                if (! $version) {
                    $manual = ManualFuncion::firstOrCreate(['codigo' => self::MANUAL], [
                        'nombre' => 'Manual de Funciones y Competencias Laborales - Villavicencio',
                    ]);
                    $version = $manual->versiones()->create([
                        'version' => self::VERSION, 'acto_tipo' => 'Decreto', 'acto_numero' => '1000-24/015',
                        'acto_fecha' => null, 'vigencia_desde' => null, 'vigencia_hasta' => null,
                        'acto_referencia' => self::FUENTE, 'estado' => 'borrador', 'created_by' => $userId,
                        'metadata_manual' => ['fecha_portada' => '2023-01-13', 'fecha_expedicion' => null,
                            'pendientes' => ['Confirmar expedición y vigencia', 'Conciliar Excel', 'Completar campos omitidos por JSON'],
                            'funciones_comunes_disponibles' => false, 'version_identificador' => 'Referencia del acto; no numeración documental inventada'],
                    ]);
                }
                foreach ($cargos as &$item) {
                    $item['model'] ??= Cargo::create($item['attributes'] + ['estado' => true]);
                }
                unset($item);
                foreach ($records as $dto) {
                    if (! $changes[$dto->origen['perfil_id']]) {
                        continue;
                    }
                    $c = $dto->cargo();
                    $ficha = $existing->get($dto->origen['perfil_id']) ?? new ManualCargoVersion;
                    $ficha->fill($dto->atributos() + ['manual_funciones_version_id' => $version->id,
                        'cargo_id' => $cargos[$c['codigo'].'/'.$c['grado']]['model']->id])->save();
                    foreach ($dto->funciones() as $funcion) {
                        $ficha->funciones()->updateOrCreate(['orden' => $funcion['orden']], $funcion);
                    }
                }
                $summary['version_id'] = $version->id;
                $this->registrar($summary, $version->id, $userId);
            };
            if ($dryRun) {
                $operation();
            } else {
                DB::transaction($operation);
                if ($summary['errores']) {
                    $this->registrar($summary, $version?->id, $userId);
                }
            }
        } catch (\Throwable $e) {
            $summary['errores'][] = $e->getMessage();
            $summary['resultado'] = 'rechazado';
            if (! $dryRun) {
                $this->registrar($summary, $version?->exists ? $version->id : null, $userId);
            }
        }

        return $summary;
    }

    private function igual(ManualCargoVersion $ficha, FichaManualData $dto): bool
    {
        foreach ($dto->atributos() as $key => $value) {
            if ($key === 'metadata_manual') {
                if (FichaManualData::hash($ficha->$key ?? []) !== FichaManualData::hash($value)) {
                    return false;
                }
            } elseif ($value !== $ficha->$key) {
                return false;
            }
        }

        return $ficha->funciones->map(fn ($f) => $f->only(['orden', 'descripcion', 'numero_fuente', 'grupo']))->all() === $dto->funciones();
    }

    private function registrar(array $summary, ?int $version, ?int $user): void
    {
        DB::table('manual_importaciones')->insert([
            'manual_funciones_version_id' => $version, 'archivo_fuente' => $summary['archivo'],
            'sha256' => $summary['sha256'], 'numero_registros' => $summary['registros_encontrados'],
            'importador' => $user ? 'user:'.$user : 'artisan:manual:import', 'user_id' => $user,
            'resultado' => $summary['resultado'], 'detalle' => json_encode($summary, JSON_THROW_ON_ERROR),
            'fecha_importacion' => now(),
        ]);
    }
}
