<?php

namespace App\Services;

use App\Models\ManualCargoVersion;
use App\Models\ManualFuncionVersion;
use DomainException;
use Illuminate\Support\Str;

/** Comparación semántica entre versiones; source_id nunca participa en el emparejamiento. */
class CompararVersionesManualService
{
    public function comparar(ManualFuncionVersion $from, ManualFuncionVersion $to): array
    {
        if ($from->manual_funciones_id !== $to->manual_funciones_id || $from->id === $to->id) {
            throw new DomainException('MANUAL_VERSIONES_INCOMPATIBLES');
        }

        $anteriores = $from->cargos()->with('cargo', 'funciones')->get();
        $nuevas = $to->cargos()->with('cargo', 'funciones')->get();
        $usadas = [];
        $fichas = [];

        foreach ($anteriores as $anterior) {
            $exactas = $nuevas->filter(fn ($nueva) => $this->fingerprintContenido($anterior) === $this->fingerprintContenido($nueva));
            if ($exactas->count() === 1 && ! isset($usadas[$exactas->first()->id])) {
                $nueva = $exactas->first();
                $usadas[$nueva->id] = true;
                $fichas[] = $this->entrada('SIN_CAMBIOS', $anterior, $nueva, 100, []);

                continue;
            }

            $candidatas = $nuevas->reject(fn ($nueva) => isset($usadas[$nueva->id]))
                ->map(fn ($nueva) => ['ficha' => $nueva, 'puntaje' => $this->puntaje($anterior, $nueva)])
                ->filter(fn ($item) => $item['puntaje'] >= 55)
                ->sortByDesc('puntaje')->values();

            if ($candidatas->isEmpty()) {
                $fichas[] = $this->entrada('RETIRADA', $anterior, null, 0, []);

                continue;
            }

            $primera = $candidatas->first();
            $segunda = $candidatas->get(1);
            if ($segunda && ($primera['puntaje'] - $segunda['puntaje']) < 12) {
                $fichas[] = $this->entrada('AMBIGUA', $anterior, null, $primera['puntaje'], [],
                    $candidatas->take(5)->map(fn ($item) => $this->resumen($item['ficha']) + ['puntaje' => $item['puntaje']])->all());

                continue;
            }

            $nueva = $primera['ficha'];
            $usadas[$nueva->id] = true;
            $fichas[] = $this->entrada('MODIFICADA', $anterior, $nueva, $primera['puntaje'], $this->diferencias($anterior, $nueva));
        }

        foreach ($nuevas as $nueva) {
            if (! isset($usadas[$nueva->id])) {
                $fichas[] = $this->entrada('NUEVA', null, $nueva, 0, []);
            }
        }

        $conteos = array_fill_keys(['SIN_CAMBIOS', 'MODIFICADA', 'NUEVA', 'RETIRADA', 'AMBIGUA'], 0);
        foreach ($fichas as $ficha) {
            $conteos[$ficha['clasificacion']]++;
        }

        return [
            'from' => $this->version($from), 'to' => $this->version($to), 'conteos' => $conteos,
            'fichas' => $fichas,
            'estrategia' => 'fingerprint de contenido + identidad descriptiva ponderada; source_id excluido del matching',
        ];
    }

    private function puntaje(ManualCargoVersion $a, ManualCargoVersion $b): float
    {
        $score = 0;
        $score += $this->igual($a->cargo->codigo, $b->cargo->codigo) ? 22 : 0;
        $score += $this->igual($a->cargo->grado, $b->cargo->grado) ? 16 : 0;
        $score += $this->similitud($a->denominacion_fuente ?? $a->cargo->denominacion, $b->denominacion_fuente ?? $b->cargo->denominacion) * 18;
        $score += $this->similitud($a->dependencia, $b->dependencia) * 12;
        $score += $this->similitud($a->area_funcional, $b->area_funcional) * 12;
        $score += $this->similitud($a->proposito_principal, $b->proposito_principal) * 14;
        $score += $this->igual($a->metadata_manual['nivel'] ?? $a->cargo->nivel, $b->metadata_manual['nivel'] ?? $b->cargo->nivel) ? 6 : 0;

        return round($score, 2);
    }

    private function diferencias(ManualCargoVersion $a, ManualCargoVersion $b): array
    {
        $antes = $this->contenido($a);
        $despues = $this->contenido($b);
        $result = [];
        foreach ($antes as $campo => $valor) {
            if ($valor !== $despues[$campo]) {
                $result[$campo] = ['antes' => $valor, 'despues' => $despues[$campo]];
            }
        }

        return $result;
    }

    private function contenido(ManualCargoVersion $ficha): array
    {
        return [
            'codigo' => $ficha->cargo->codigo, 'grado' => $ficha->cargo->grado,
            'denominacion' => $ficha->denominacion_fuente ?? $ficha->cargo->denominacion,
            'dependencia' => $ficha->dependencia, 'area_funcional' => $ficha->area_funcional,
            'proposito' => $ficha->proposito_principal, 'requisitos' => $ficha->requisitos,
            'funciones' => $ficha->funciones->map->only(['orden', 'descripcion', 'numero_fuente', 'grupo'])->values()->all(),
            'conocimientos' => $ficha->metadata_manual['conocimientos'] ?? [],
            'metadata' => collect($ficha->metadata_manual ?? [])->except(['perfil_id', 'source_id'])->all(),
        ];
    }

    private function fingerprintContenido(ManualCargoVersion $ficha): string
    {
        return hash('sha256', json_encode($this->normalizarValor($this->contenido($ficha)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function normalizarValor(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($item) => $this->normalizarValor($item), $value);
        }

        return is_string($value) ? $this->normalizar($value) : $value;
    }

    private function similitud(?string $a, ?string $b): float
    {
        $a = $this->normalizar($a);
        $b = $this->normalizar($b);
        if ($a === $b) {
            return 1;
        }
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    private function igual(?string $a, ?string $b): bool
    {
        return $this->normalizar($a) === $this->normalizar($b);
    }

    private function normalizar(?string $value): string
    {
        return (string) Str::of($value ?? '')->ascii()->lower()->squish();
    }

    private function entrada(string $clasificacion, ?ManualCargoVersion $anterior, ?ManualCargoVersion $nueva,
        float $puntaje, array $diferencias, array $candidatas = []): array
    {
        return [
            'clasificacion' => $clasificacion, 'anterior' => $anterior ? $this->resumen($anterior) : null,
            'nueva' => $nueva ? $this->resumen($nueva) : null, 'puntaje' => $puntaje,
            'diferencias' => $diferencias, 'candidatas' => $candidatas,
        ];
    }

    private function resumen(ManualCargoVersion $ficha): array
    {
        return [
            'id' => $ficha->id, 'source_id' => $ficha->source_id, 'codigo' => $ficha->cargo->codigo,
            'grado' => $ficha->cargo->grado, 'denominacion' => $ficha->denominacion_fuente ?? $ficha->cargo->denominacion,
            'dependencia' => $ficha->dependencia, 'area_funcional' => $ficha->area_funcional,
        ];
    }

    private function version(ManualFuncionVersion $version): array
    {
        return ['id' => $version->id, 'version' => $version->version, 'estado' => $version->estado];
    }
}
