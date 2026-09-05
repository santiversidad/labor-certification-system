<?php

namespace App\Domain\Manual;

/** Pure comparison: never changes a source, assignment, manual or certificate. */
final class CompararManualesService
{
    public function comparar(array $json, array $excel): array
    {
        $identidad = ['codigo', 'grado', 'denominacion', 'nivel', 'dependencia', 'area_funcional',
            'proposito_principal', 'pagina_inicio', 'pagina_fin', 'registro_decreto'];
        $candidatos = [];
        foreach ($json as $j => $dto) {
            $exactos = [];
            $documentales = [];
            foreach ($excel as $e => $other) {
                $a = $dto->origen;
                $b = $other->origen;
                if ($this->igualEn($a, $b, $identidad)) {
                    $exactos[] = $e;
                } elseif ($this->igualEn($a, $b, ['pagina_inicio', 'registro_decreto'])
                    && count(array_filter(['codigo', 'grado', 'denominacion', 'nivel', 'dependencia'], fn ($k) => $a[$k] === $b[$k])) >= 3) {
                    $documentales[] = $e;
                }
            }
            $candidatos[$j] = $exactos ?: $documentales;
        }
        $claims = [];
        foreach ($candidatos as $j => $indices) {
            foreach ($indices as $e) {
                $claims[$e][] = $j;
            }
        }
        $result = ['conteos' => array_fill_keys(['COINCIDE', 'SOLO_JSON', 'SOLO_EXCEL', 'DIFERENCIA_CAMPO', 'DIFERENCIA_FUNCIONES', 'AMBIGUA'], 0),
            'fichas' => [], 'diferencias' => [], 'duplicados' => ['json' => $this->duplicados($json), 'excel' => $this->duplicados($excel)]];
        $usados = [];
        foreach ($json as $j => $dto) {
            $indices = $candidatos[$j];
            $entry = ['ficha' => $dto->origen['perfil_id'], 'pagina' => $dto->origen['pagina_inicio'],
                'candidatos_excel' => array_map(fn ($i) => $excel[$i]->origen['perfil_id'], $indices),
                'matching' => 'identidad descriptiva + anclas documentales; perfil_id no decide'];
            if (count($indices) === 0) {
                $status = 'SOLO_JSON';
            } elseif (count($indices) > 1 || count($claims[$indices[0]]) > 1) {
                $status = 'AMBIGUA';
                foreach ($indices as $e) {
                    $usados[$e] = true;
                }
            } else {
                $e = $indices[0];
                $usados[$e] = true;
                $other = $excel[$e];
                $diffs = $this->diferencias($dto->origen, $other->origen);
                $funciones = false;
                foreach ($diffs as $diff) {
                    $funciones = $funciones || str_starts_with($diff['campo'], 'funciones');
                    $result['diferencias'][] = $entry + $diff + ['excel_ficha' => $other->origen['perfil_id']];
                }
                $status = $diffs ? ($funciones ? 'DIFERENCIA_FUNCIONES' : 'DIFERENCIA_CAMPO') : 'COINCIDE';
                $entry['excel_ficha'] = $other->origen['perfil_id'];
                $entry['excel_ubicacion'] = $other->trazabilidad['ubicacion'] ?? null;
                $entry['funciones_json'] = count($dto->origen['funciones']);
                $entry['funciones_excel'] = count($other->origen['funciones']);
                $entry['campos_diferentes'] = array_column($diffs, 'campo');
            }
            $result['conteos'][$status]++;
            $result['fichas'][] = $entry + ['resultado' => $status];
        }
        foreach ($excel as $e => $dto) {
            if (! isset($usados[$e])) {
                $result['conteos']['SOLO_EXCEL']++;
                $result['fichas'][] = ['ficha' => $dto->origen['perfil_id'], 'resultado' => 'SOLO_EXCEL'];
            }
        }

        return $result;
    }

    private function igualEn(array $a, array $b, array $keys): bool
    {
        foreach ($keys as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function diferencias(mixed $a, mixed $b, string $path = ''): array
    {
        if ($a === $b) {
            return [];
        }
        if (! is_array($a) || ! is_array($b)) {
            return [['campo' => $path, 'json' => $a, 'excel' => $b]];
        }
        $result = [];
        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $key) {
            $child = $path === '' ? (string) $key : $path.'.'.$key;
            if (! array_key_exists($key, $a) || ! array_key_exists($key, $b)) {
                $result[] = ['campo' => $child, 'json' => $a[$key] ?? null, 'excel' => $b[$key] ?? null,
                    'ausente_en' => ! array_key_exists($key, $a) ? 'JSON' : 'Excel'];
            } else {
                array_push($result, ...$this->diferencias($a[$key], $b[$key], $child));
            }
        }

        return $result;
    }

    private function duplicados(array $dtos): array
    {
        $ids = [];
        $functions = [];
        foreach ($dtos as $dto) {
            $id = $dto->origen['perfil_id'];
            $ids[$id] = ($ids[$id] ?? 0) + 1;
            $texts = [];
            $orders = [];
            foreach ($dto->origen['funciones'] as $f) {
                $texts[$f['texto']][] = $f['orden'];
                $orders[$f['orden']] = ($orders[$f['orden']] ?? 0) + 1;
            }
            foreach ($texts as $text => $indices) {
                if (count($indices) > 1) {
                    $functions[] = ['ficha' => $id, 'tipo' => 'texto_repetido', 'ordenes' => $indices];
                }
            }
            foreach ($orders as $order => $count) {
                if ($count > 1) {
                    $functions[] = ['ficha' => $id, 'tipo' => 'orden_repetido', 'orden' => $order];
                }
            }
        }

        return ['source_ids' => array_keys(array_filter($ids, fn ($n) => $n > 1)), 'funciones' => $functions];
    }
}
