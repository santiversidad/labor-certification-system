<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Domain\SalaryScale\SalaryScaleAmount;
use App\Models\Cargo;
use App\Models\EscalaSalarialVigencia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryScaleImportService
{
    public const HEADERS = ['anio', 'codigo', 'grado', 'denominacion', 'numero_cargos', 'asignacion_basica_mensual'];

    public function inspect(string $file): array
    {
        $summary = ['filas_leidas' => 0, 'validas' => 0, 'duplicadas' => 0, 'sin_cargo_relacionado' => 0, 'errores' => [], 'vigencias' => [], 'filas' => []];
        $stream = fopen($file, 'rb');
        if (! $stream) {
            throw ValidationException::withMessages(['file' => 'No se pudo abrir el archivo.']);
        }
        try {
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if ($headers) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            if ($headers !== self::HEADERS) {
                $summary['errores'][] = 'Los encabezados deben coincidir exactamente con la plantilla CSV.';

                return $summary;
            }
            $seen = [];
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $summary['filas_leidas']++;
                $line = $summary['filas_leidas'] + 1;
                if ($summary['filas_leidas'] > 10000) {
                    $summary['errores'][] = 'Máximo preventivo: 10.000 filas por archivo.';
                    break;
                }
                if (count($row) !== count(self::HEADERS)) {
                    $summary['errores'][] = 'Fila '.$line.': número de columnas incorrecto.';

                    continue;
                }
                $entry = array_combine(self::HEADERS, $row);
                $valid = preg_match('/^\d{4}$/D', $entry['anio']) && (int) $entry['anio'] >= 1900 && (int) $entry['anio'] <= 2199
                    && preg_match('/^\d{1,10}$/D', $entry['codigo']) && preg_match('/^\d{1,5}$/D', $entry['grado'])
                    && trim($entry['denominacion']) !== '' && mb_strlen($entry['denominacion']) <= 200
                    && ($entry['numero_cargos'] === '' || (preg_match('/^\d{1,7}$/D', $entry['numero_cargos']) && (int) $entry['numero_cargos'] <= 1000000))
                    && preg_match('/^[1-9]\d{0,11}$/D', $entry['asignacion_basica_mensual'])
                    && (int) $entry['asignacion_basica_mensual'] <= SalaryScaleAmount::MAX;
                if (! $valid) {
                    $summary['errores'][] = 'Fila '.$line.': año, código, grado, denominación, número de cargos o moneda inválidos. Use pesos enteros sin símbolos ni separadores.';

                    continue;
                }
                $key = json_encode([$entry['anio'], $entry['codigo'], $entry['grado'], $entry['denominacion']]);
                if (isset($seen[$key])) {
                    $summary['duplicadas']++;
                    $summary['errores'][] = 'Fila '.$line.': combinación duplicada dentro de la vigencia.';

                    continue;
                }
                $seen[$key] = true;
                $matches = Cargo::where('codigo', $entry['codigo'])->where('grado', $entry['grado'])->where('denominacion', $entry['denominacion'])->get();
                $entry['cargo_id'] = $matches->count() === 1 ? $matches->sole()->id : null;
                if ($entry['cargo_id'] === null) {
                    $summary['sin_cargo_relacionado']++;
                }
                $entry['anio'] = (int) $entry['anio'];
                $entry['numero_cargos'] = $entry['numero_cargos'] === '' ? null : (int) $entry['numero_cargos'];
                $summary['filas'][] = $entry;
                $summary['validas']++;
                $summary['vigencias'][] = $entry['anio'];
            }
            if (! $summary['filas_leidas']) {
                $summary['errores'][] = 'La plantilla no contiene datos. Falta la transcripción revisada contra la fuente institucional.';
            }
            $summary['vigencias'] = array_values(array_unique($summary['vigencias']));
            sort($summary['vigencias']);

            return $summary;
        } finally {
            fclose($stream);
        }
    }

    public function import(array $summary, int $actor, string $source): array
    {
        if ($summary['errores']) {
            throw ValidationException::withMessages(['file' => $summary['errores']]);
        }

        return DB::transaction(function () use ($summary, $actor, $source) {
            // Serialize imports and year creation; source values never replace a populated year.
            DB::select('SELECT pg_advisory_xact_lock(?)', [73052025]);
            $created = 0;
            foreach ($summary['vigencias'] as $number) {
                $rows = array_values(array_filter($summary['filas'], fn ($entry) => $entry['anio'] === $number));
                $year = EscalaSalarialVigencia::where('anio', $number)->lockForUpdate()->first();
                if ($year && $year->items()->exists()) {
                    $existing = $year->items()->get();
                    $identical = $existing->count() === count($rows) && $year->fuente === $source;
                    foreach ($rows as $row) {
                        $item = $existing->first(fn ($item) => $item->codigo === $row['codigo'] && $item->grado === $row['grado'] && $item->denominacion === $row['denominacion']);
                        $identical = $identical && $item && $item->asignacion_basica_mensual === $row['asignacion_basica_mensual'] && $item->numero_cargos === $row['numero_cargos'] && $item->cargo_id === $row['cargo_id'];
                    }
                    if (! $identical) {
                        throw ValidationException::withMessages(['file' => 'La vigencia '.$number.' ya contiene datos diferentes. La importación no sobrescribe históricos ni borradores.']);
                    }

                    continue;
                }
                if ($year) {
                    app(SalaryScaleService::class)->draft($year);
                    $year->update(['fuente' => $source]);
                } else {
                    $year = EscalaSalarialVigencia::create(['anio' => $number, 'fuente' => $source, 'creado_por' => $actor]);
                }
                foreach ($rows as $row) {
                    $year->items()->create(array_diff_key($row, ['anio' => true]) + ['origen_valor' => 'importado']);
                }
                $year->increment('revision');
                app(RegistrarAuditoriaAction::class)->execute('salary_scale_year_imported', 'EscalaSalarialVigencia', $year->id,
                    'Valores fuente importados en borrador; requieren revisión antes de publicar.', ['anio' => $number, 'cantidad' => count($rows), 'sin_cargo_relacionado' => count(array_filter($rows, fn ($row) => $row['cargo_id'] === null))], $actor);
                $created += count($rows);
            }

            return ['filas_importadas' => $created, 'sin_cambios' => $created === 0];
        });
    }
}
