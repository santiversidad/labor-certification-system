<?php

namespace App\Services;

use App\Models\FuncionarioCargo;
use App\Models\ManualActualizacionAsignacion;
use App\Models\ManualCargoLineage;
use App\Models\ManualFuncionVersion;
use DomainException;
use Illuminate\Support\Facades\DB;

class PlanificarActualizacionManualService
{
    public function __construct(private readonly CompararVersionesManualService $comparador) {}

    public function ejecutar(ManualFuncionVersion $from, ManualFuncionVersion $to): array
    {
        if ($to->estado !== 'borrador') {
            throw new DomainException('MANUAL_DESTINO_DEBE_SER_BORRADOR');
        }

        return DB::transaction(function () use ($from, $to) {
            $diff = $this->comparador->comparar($from, $to);
            $porAnterior = [];

            foreach ($diff['fichas'] as $entry) {
                $anteriorId = $entry['anterior']['id'] ?? null;
                if ($anteriorId) {
                    $porAnterior[$anteriorId] = $entry;
                }
                if (! $anteriorId || ! ($entry['nueva']['id'] ?? null)
                    || ! in_array($entry['clasificacion'], ['SIN_CAMBIOS', 'MODIFICADA'], true)) {
                    continue;
                }
                ManualCargoLineage::updateOrCreate(
                    ['predecessor_id' => $anteriorId, 'successor_id' => $entry['nueva']['id']],
                    ['clasificacion' => $entry['clasificacion'], 'estado' => 'propuesto',
                        'puntaje' => $entry['puntaje'], 'diferencias' => $entry['diferencias']],
                );
            }

            $asignaciones = FuncionarioCargo::query()
                ->where(function ($q) use ($from) {
                    $q->whereHas('fichasNormativas.fichaManual', fn ($f) => $f->where('manual_funciones_version_id', $from->id))
                        ->orWhereHas('fichaManual', fn ($f) => $f->where('manual_funciones_version_id', $from->id));
                })->with(['fichasNormativas.fichaManual', 'fichaManual'])->get();

            $conteos = array_fill_keys(['AUTO_MIGRABLE', 'REQUIERE_REVISION', 'SIN_EQUIVALENTE'], 0);
            foreach ($asignaciones as $asignacion) {
                $anterior = $asignacion->fichasNormativas->first(
                    fn ($rel) => $rel->fichaManual?->manual_funciones_version_id === $from->id
                )?->fichaManual ?? $asignacion->fichaManual;
                if (! $anterior) {
                    continue;
                }
                $entry = $porAnterior[$anterior->id] ?? null;
                $candidateId = $entry['nueva']['id'] ?? null;
                $clasificacion = match ($entry['clasificacion'] ?? 'RETIRADA') {
                    'SIN_CAMBIOS' => 'AUTO_MIGRABLE',
                    'MODIFICADA' => $this->cambiaUbicacion($entry) ? 'REQUIERE_REVISION' : 'AUTO_MIGRABLE',
                    'AMBIGUA' => 'REQUIERE_REVISION',
                    default => 'SIN_EQUIVALENTE',
                };
                $motivos = match ($clasificacion) {
                    'AUTO_MIGRABLE' => ['coincidencia_inequivoca', 'sin_cambio_de_dependencia_o_area'],
                    'REQUIERE_REVISION' => $entry['clasificacion'] === 'AMBIGUA'
                        ? ['multiples_fichas_candidatas'] : ['cambio_dependencia_o_area'],
                    default => ['ficha_retirada_sin_equivalente'],
                };
                $existente = ManualActualizacionAsignacion::where('to_version_id', $to->id)
                    ->where('funcionario_cargo_id', $asignacion->id)->first();
                if ($existente?->resolved_at && $existente->ficha_candidata_id) {
                    $conteos[$existente->clasificacion]++;

                    continue;
                }
                ManualActualizacionAsignacion::updateOrCreate(
                    ['to_version_id' => $to->id, 'funcionario_cargo_id' => $asignacion->id],
                    ['from_version_id' => $from->id, 'ficha_anterior_id' => $anterior->id,
                        'ficha_candidata_id' => $candidateId, 'clasificacion' => $clasificacion, 'motivos' => $motivos],
                );
                $conteos[$clasificacion]++;
            }

            return ['diff' => $diff, 'asignaciones' => $conteos, 'total_asignaciones' => array_sum($conteos)];
        });
    }

    private function cambiaUbicacion(array $entry): bool
    {
        return isset($entry['diferencias']['dependencia']) || isset($entry['diferencias']['area_funcional']);
    }
}
