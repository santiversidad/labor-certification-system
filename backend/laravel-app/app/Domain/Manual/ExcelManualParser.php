<?php

namespace App\Domain\Manual;

use DomainException;
use PharData;
use SimpleXMLElement;

/** Read-only adapter for the observed four-sheet XLSX schema. No Excel import writes. */
final class ExcelManualParser
{
    public const PERFILES = ['Perfil ID' => 'perfil_id', 'Registro decreto' => 'registro_decreto',
        'Nivel' => 'nivel', 'Denominación del empleo' => 'denominacion', 'Código' => 'codigo', 'Grado' => 'grado',
        'No. de cargos' => 'numero_cargos', 'Dependencia' => 'dependencia', 'Cargo del jefe inmediato' => 'jefe_inmediato',
        'Área funcional' => 'area_funcional', 'Propósito principal' => 'proposito_principal',
        'Página inicio' => 'pagina_inicio', 'Página fin' => 'pagina_fin', 'Fuente' => 'fuente'];

    public const FUNCIONES = ['Perfil ID', 'Registro decreto', 'Nivel', 'Denominación', 'Código', 'Grado',
        'Área funcional', 'Orden', 'Número en fuente', 'Grupo/Subgrupo', 'Función esencial', 'Página inicio perfil', 'Página fin perfil'];

    public const CONOCIMIENTOS = ['Perfil ID', 'Registro decreto', 'Denominación', 'Código', 'Grado',
        'Área funcional', 'Número', 'Conocimiento básico esencial'];

    public function __construct(private readonly JsonManualParser $jsonParser) {}

    /** @return list<FichaManualData> */
    public function parse(string $archivo): array
    {
        $workbook = $this->inspect($archivo, includeCellDetails: false);
        $profiles = $this->rows($workbook['hojas']['Perfiles_Cargo'], array_keys(self::PERFILES));
        $records = [];
        $locations = [];
        foreach ($profiles as $row => $values) {
            $r = [];
            foreach (self::PERFILES as $header => $field) {
                $r[$field] = $values[$header];
            }
            if (isset($records[$r['perfil_id']])) {
                throw new DomainException('MANUAL_EXCEL_PERFIL_DUPLICADO: '.$r['perfil_id']);
            }
            $r['funciones'] = [];
            $r['conocimientos'] = [];
            $records[$r['perfil_id']] = $r;
            $locations[$r['perfil_id']] = ['perfil_fila' => $row, 'funciones_filas' => [], 'conocimientos_filas' => []];
        }
        foreach (['Funciones' => self::FUNCIONES, 'Conocimientos' => self::CONOCIMIENTOS] as $name => $headers) {
            foreach ($this->rows($workbook['hojas'][$name], $headers) as $row => $values) {
                $id = $values['Perfil ID'];
                if (! isset($records[$id])) {
                    throw new DomainException("MANUAL_EXCEL_HUERFANA: {$name}!{$row} {$id}");
                }
                // Redundant sheet fields must agree, rather than blindly joining by Perfil ID.
                $cross = ['Registro decreto' => 'registro_decreto', 'Denominación' => 'denominacion',
                    'Código' => 'codigo', 'Grado' => 'grado', 'Área funcional' => 'area_funcional'];
                if ($name === 'Funciones') {
                    $cross += ['Nivel' => 'nivel', 'Página inicio perfil' => 'pagina_inicio', 'Página fin perfil' => 'pagina_fin'];
                }
                foreach ($cross as $header => $field) {
                    if ($values[$header] !== $records[$id][$field]) {
                        throw new DomainException("MANUAL_EXCEL_REFERENCIA_INCONSISTENTE: {$name}!{$row} {$id}.{$field}");
                    }
                }
                if ($name === 'Funciones') {
                    $records[$id]['funciones'][] = ['orden' => $values['Orden'], 'numero_fuente' => $values['Número en fuente'],
                        'grupo' => $values['Grupo/Subgrupo'], 'texto' => $values['Función esencial']];
                    $locations[$id]['funciones_filas'][] = $row;
                } else {
                    $records[$id]['conocimientos'][] = ['numero' => $values['Número'], 'texto' => $values['Conocimiento básico esencial']];
                    $locations[$id]['conocimientos_filas'][] = $row;
                }
            }
        }
        // Shared structural/business validation and shared DTO; never duplicate import rules.
        $dtos = $this->jsonParser->parse(json_encode(array_values($records), JSON_THROW_ON_ERROR));

        return array_map(fn ($dto) => new FichaManualData($dto->origen, [
            'formato' => 'xlsx', 'archivo' => basename($archivo), 'sha256' => $workbook['sha256'],
            'ubicacion' => $locations[$dto->origen['perfil_id']],
            'leeme' => $workbook['hojas']['LEEME']['filas'],
        ]), $dtos);
    }

    public function inspect(string $archivo, bool $includeCellDetails = true): array
    {
        if (! is_file($archivo) || filesize($archivo) > 20_000_000 || ! preg_match('/\.xlsx$/i', $archivo)) {
            throw new DomainException('MANUAL_EXCEL_ARCHIVO_INVALIDO: se requiere XLSX de hasta 20 MB.');
        }
        $hash = hash_file('sha256', $archivo);
        try {
            $zip = new PharData($archivo);
            $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
            $targets = [];
            foreach ($rels->children('http://schemas.openxmlformats.org/package/2006/relationships') as $r) {
                $attributes = $r->attributes();
                $target = (string) $attributes['Target'];
                if ((string) $attributes['TargetMode'] === 'External' || str_contains($target, '..')) {
                    throw new DomainException('MANUAL_EXCEL_RELACION_EXTERNA');
                }
                $targets[(string) $attributes['Id']] = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
            $shared = [];
            if (isset($zip['xl/sharedStrings.xml'])) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('//*[local-name()="si"]') as $item) {
                    $shared[] = $this->textos($item);
                }
            }
            $result = ['archivo' => basename($archivo), 'sha256' => $hash, 'tamano' => filesize($archivo), 'hojas' => []];
            foreach ($this->xml($zip, 'xl/workbook.xml')->xpath('//*[local-name()="sheet"]') as $sheet) {
                $name = (string) $sheet['name'];
                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                if (! isset($targets[$rid]) || isset($result['hojas'][$name])) {
                    throw new DomainException('MANUAL_EXCEL_HOJA_INVALIDA');
                }
                $xml = $this->xml($zip, $targets[$rid]);
                $rows = [];
                $types = [];
                $raw = [];
                $merges = [];
                $formulas = [];
                foreach ($xml->xpath('//*[local-name()="row"]/*[local-name()="c"]') as $cell) {
                    $ref = (string) $cell['r'];
                    if (! preg_match('/^([A-Z]+)([1-9][0-9]*)$/', $ref, $m) || (int) $m[2] > 20000) {
                        throw new DomainException('MANUAL_EXCEL_CELDA_INVALIDA');
                    }
                    $type = (string) $cell['t'];
                    $valueNodes = $cell->xpath('./*[local-name()="v"]');
                    $value = $valueNodes ? (string) $valueNodes[0] : null;
                    if ($cell->xpath('./*[local-name()="f"]')) {
                        $formulas[] = $ref;
                    }
                    if ($includeCellDetails) {
                        $raw[$ref] = ['tipo_ooxml' => $type ?: 'n', 'valor_xml' => $value];
                    }
                    $decoded = match ($type) {
                        's' => $shared[$value] ?? throw new DomainException('MANUAL_EXCEL_STRING_INVALIDO'),
                        'inlineStr' => $this->textos($cell),
                        'str' => $value ?? '', // An OOXML empty string, not an inferred cell value.
                        '', 'n' => $value === null ? null : (preg_match('/^-?[0-9]+$/', $value) ? (int) $value : (float) $value),
                        default => throw new DomainException("MANUAL_EXCEL_TIPO_NO_SOPORTADO: {$name}!{$ref}"),
                    };
                    if (isset($rows[(int) $m[2]]) && array_key_exists($m[1], $rows[(int) $m[2]])) {
                        throw new DomainException('MANUAL_EXCEL_CELDA_DUPLICADA');
                    }
                    $rows[(int) $m[2]][$m[1]] = $decoded;
                    if ($includeCellDetails) {
                        $types[$ref] = get_debug_type($decoded);
                    }
                }
                foreach ($xml->xpath('//*[local-name()="mergeCell"]') as $merge) {
                    $merges[] = (string) $merge['ref'];
                }
                if ($formulas || ($name !== 'LEEME' && $merges)) {
                    throw new DomainException('MANUAL_EXCEL_FORMULAS_O_COMBINADAS: '.$name);
                }
                ksort($rows);
                $result['hojas'][$name] = ['filas' => $rows, 'tipos' => $types, 'celdas_origen' => $raw,
                    'combinadas' => $merges, 'formulas' => $formulas];
            }
            $names = array_keys($result['hojas']);
            sort($names);
            if ($names !== ['Conocimientos', 'Funciones', 'LEEME', 'Perfiles_Cargo']) {
                throw new DomainException('MANUAL_EXCEL_ESQUEMA_NO_SOPORTADO: hojas nuevas requieren revisión; no se descartan.');
            }
            if (hash_file('sha256', $archivo) !== $hash) {
                throw new DomainException('MANUAL_EXCEL_CAMBIO_DURANTE_LECTURA');
            }

            return $result;
        } catch (DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DomainException('MANUAL_EXCEL_INVALIDO: '.$e->getMessage(), previous: $e);
        }
    }

    private function rows(array $sheet, array $headers): array
    {
        $actual = $sheet['filas'][1] ?? [];
        $sorted = array_values($actual);
        sort($sorted);
        $expected = $headers;
        sort($expected);
        if ($sorted !== $expected) {
            throw new DomainException('MANUAL_EXCEL_ENCABEZADOS_INVALIDOS: no se ignoran campos desconocidos.');
        }
        $rows = [];
        foreach ($sheet['filas'] as $row => $cells) {
            if ($row === 1) {
                continue;
            }
            foreach ($cells as $col => $value) {
                if (! array_key_exists($col, $actual)) {
                    throw new DomainException('MANUAL_EXCEL_COLUMNA_SIN_ENCABEZADO');
                }
            }
            foreach ($actual as $col => $header) {
                $rows[$row][$header] = $cells[$col] ?? null;
            }
        }

        return $rows;
    }

    private function xml(PharData $zip, string $path): SimpleXMLElement
    {
        if (! isset($zip[$path]) || $zip[$path]->getSize() > 16_000_000) {
            throw new DomainException('MANUAL_EXCEL_XML_AUSENTE_O_EXCESIVO: '.$path);
        }
        $bytes = $zip[$path]->getContent();
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $bytes)) {
            throw new DomainException('MANUAL_EXCEL_XML_ENTIDADES_PROHIBIDAS');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($bytes, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw new DomainException('MANUAL_EXCEL_XML_INVALIDO');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function textos(SimpleXMLElement $node): string
    {
        return implode('', array_map(fn ($t) => (string) $t, $node->xpath('.//*[local-name()="t" and not(ancestor::*[local-name()="rPh"])]')));
    }
}
