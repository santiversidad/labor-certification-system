<?php

namespace App\Services;

use App\Domain\Manual\ExcelManualParser;
use App\Domain\Manual\JsonManualParser;
use App\Models\Cargo;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncionVersion;
use DomainException;
use Illuminate\Support\Facades\DB;

class ImportarBorradorManualService
{
    public function __construct(
        private readonly JsonManualParser $json,
        private readonly ExcelManualParser $excel,
    ) {}

    public function ejecutar(ManualFuncionVersion $version, string $archivo, string $nombre,
        bool $dryRun, ?int $actorId): array
    {
        if ($version->estado !== 'borrador') {
            throw new DomainException('MANUAL_VERSION_IMMUTABLE');
        }
        if (! is_file($archivo) || ! is_readable($archivo)) {
            throw new DomainException('MANUAL_ARCHIVO_NO_LEIBLE');
        }
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $dtos = match ($extension) {
            'json' => $this->json->parse(file_get_contents($archivo)),
            'xlsx' => $this->excel->parse($archivo),
            default => throw new DomainException('MANUAL_FORMATO_NO_SOPORTADO'),
        };
        $sourceIds = array_map(fn ($dto) => $dto->origen['perfil_id'], $dtos);
        if (count($sourceIds) !== count(array_unique($sourceIds))) {
            throw new DomainException('MANUAL_SOURCE_ID_DUPLICADO_EN_VERSION');
        }

        $existentes = $version->cargos()->with('funciones')->get()->keyBy('source_id');
        $summary = [
            'version_id' => $version->id, 'archivo' => $nombre, 'sha256' => hash_file('sha256', $archivo),
            'dry_run' => $dryRun, 'fichas' => count($dtos),
            'funciones' => array_sum(array_map(fn ($dto) => count($dto->funciones()), $dtos)),
            'conocimientos' => array_sum(array_map(fn ($dto) => count($dto->origen['conocimientos'] ?? []), $dtos)),
            'nuevas' => 0, 'modificadas' => 0, 'sin_cambios' => 0, 'errores' => [],
        ];
        foreach ($dtos as $dto) {
            $actual = $existentes->get($dto->origen['perfil_id']);
            if (! $actual) {
                $summary['nuevas']++;
            } elseif ($actual->content_hash === $dto->atributos()['content_hash']) {
                $summary['sin_cambios']++;
            } else {
                $summary['modificadas']++;
            }
        }
        $ausentes = $existentes->keys()->diff($sourceIds)->values()->all();
        if ($ausentes) {
            $summary['errores'][] = ['codigo' => 'MANUAL_FICHAS_AUSENTES_EN_REIMPORTACION', 'source_ids' => $ausentes];
        }
        if ($dryRun || $summary['errores']) {
            $summary['resultado'] = $summary['errores'] ? 'rechazado' : 'dry_run';

            return $summary;
        }

        return DB::transaction(function () use ($version, $dtos, $existentes, $summary, $archivo, $nombre, $actorId) {
            $version = ManualFuncionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($version->estado !== 'borrador') {
                throw new DomainException('MANUAL_VERSION_IMMUTABLE');
            }
            foreach ($dtos as $dto) {
                $cargoData = $dto->cargo();
                $cargo = Cargo::firstOrCreate(
                    ['codigo' => $cargoData['codigo'], 'grado' => $cargoData['grado']],
                    $cargoData + ['estado' => true],
                );
                $ficha = $existentes->get($dto->origen['perfil_id']) ?? new ManualCargoVersion;
                $ficha->fill($dto->atributos() + [
                    'manual_funciones_version_id' => $version->id, 'cargo_id' => $cargo->id,
                ])->save();
                $ficha->funciones()->delete();
                $ficha->funciones()->createMany($dto->funciones());
            }
            $summary['resultado'] = 'importado';
            DB::table('manual_importaciones')->insert([
                'manual_funciones_version_id' => $version->id, 'archivo_fuente' => $nombre,
                'sha256' => hash_file('sha256', $archivo), 'numero_registros' => count($dtos),
                'importador' => $actorId ? 'user:'.$actorId : 'artisan:manual:import-draft',
                'user_id' => $actorId, 'resultado' => 'importado',
                'detalle' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'fecha_importacion' => now(),
            ]);

            return $summary;
        });
    }
}
