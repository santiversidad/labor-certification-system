<?php

namespace App\Domain\Manual;

use DomainException;
use Illuminate\Support\Facades\Validator;

final class JsonManualParser
{
    /** @return list<FichaManualData> */
    public function parse(string $bytes): array
    {
        try {
            $records = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DomainException('MANUAL_JSON_INVALIDO: '.$e->getMessage());
        }
        $trace = [];
        if (is_array($records) && ! array_is_list($records)) {
            $metadata = $records['metadata'] ?? [];
            $validator = Validator::make(is_array($metadata) ? $metadata : [], [
                'version_esquema' => 'required|in:manual-reconciliado-v1',
                'estado' => 'required|in:propuesta_no_aplicada',
                'fecha_conciliacion' => 'required|date',
                'numero_fichas' => 'required|integer|min:1',
                'numero_funciones' => 'required|integer|min:1',
                'fuentes' => 'required|array|min:3',
                'fuentes.*.archivo' => 'required|string',
                'fuentes.*.formato' => 'required|in:json,xlsx,pdf',
                'fuentes.*.sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
                'correcciones' => 'present|array',
                'pendientes' => 'present|array',
            ]);
            if ($validator->fails() || array_diff(array_keys($records), ['metadata', 'fichas'])
                || ! isset($records['fichas']) || ! is_array($records['fichas'])) {
                throw new DomainException('MANUAL_METADATA_INVALIDA: envoltura reconciliada inválida.');
            }
            $trace = ['formato' => 'json', 'sha256' => hash('sha256', $bytes), 'conciliacion' => $metadata];
            $records = $records['fichas'];
            if ($metadata['numero_fichas'] !== count($records)
                || $metadata['numero_funciones'] !== array_sum(array_map(fn ($r) => is_array($r['funciones'] ?? null) ? count($r['funciones']) : 0, $records))
                || count(array_unique(array_column($metadata['fuentes'], 'formato'))) !== 3) {
                throw new DomainException('MANUAL_METADATA_CONTEOS_O_FUENTES_INVALIDOS');
            }
        }
        if (! is_array($records) || ! array_is_list($records) || $records === []) {
            throw new DomainException('MANUAL_JSON_INVALIDO: se espera un array no vacío de fichas.');
        }
        foreach ($records as $i => $record) {
            $rules = [
                'perfil_id' => 'required|string|max:100', 'registro_decreto' => 'required|integer|min:1',
                'codigo' => 'required|string|max:10', 'grado' => 'required|string|max:5',
                'nivel' => 'required|string|max:60', 'denominacion' => 'required|string|max:150',
                'numero_cargos' => 'required|string', 'dependencia' => 'required|string',
                'jefe_inmediato' => 'required|string', 'area_funcional' => 'present|string',
                'proposito_principal' => 'present|string', 'fuente' => 'required|string',
                'pagina_inicio' => 'required|integer|min:1', 'pagina_fin' => 'required|integer|gte:pagina_inicio',
                'funciones' => 'required|array|min:1', 'funciones.*.orden' => 'required|integer|min:1|max:32767|distinct:strict',
                'funciones.*.numero_fuente' => 'present|nullable|integer|min:1', 'funciones.*.grupo' => 'present|string',
                'funciones.*.texto' => 'required|string', 'conocimientos' => 'present|array',
                'conocimientos.*.numero' => 'required|integer|min:1', 'conocimientos.*.texto' => 'required|string',
            ];
            $validator = Validator::make(is_array($record) ? $record : [], $rules);
            if ($validator->fails()) {
                throw new DomainException('MANUAL_JSON_INVALIDO: registro '.($i + 1).' '.implode(' ', $validator->errors()->all()));
            }
            if (array_column($record['funciones'], 'orden') !== range(1, count($record['funciones']))) {
                throw new DomainException('MANUAL_ORDEN_INVALIDO: '.$record['perfil_id']);
            }
        }

        return array_map(fn ($record) => new FichaManualData($record, $trace), $records);
    }
}
