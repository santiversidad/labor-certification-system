<?php

namespace App\Domain\Manual;

final readonly class FichaManualData
{
    public function __construct(public array $origen, public array $trazabilidad = []) {}

    public static function hash(array $value): string
    {
        $canonical = function (array $items) use (&$canonical): array {
            if (! array_is_list($items)) {
                ksort($items);
            }
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $item = $canonical($item);
                }
            }

            return $items;
        };

        return hash('sha256', json_encode($canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function denominacionGenerica(string $nombre, string $codigo, string $grado): string
    {
        // Only strip the exact institutional suffix observed in this source. Raw name is retained.
        return preg_replace('/\s+'.preg_quote($codigo, '/').'(?:-'.preg_quote($grado, '/').')?$/u', '', trim($nombre));
    }

    public function cargo(): array
    {
        $r = $this->origen;

        return ['codigo' => $r['codigo'], 'grado' => $r['grado'],
            'denominacion' => self::denominacionGenerica($r['denominacion'], $r['codigo'], $r['grado']),
            'nivel' => $r['nivel']];
    }

    public function identidad(): string
    {
        return self::hash(['namespace' => 'manual-perfil-v1', 'source_id' => $this->origen['perfil_id']]);
    }

    public function natural(): string
    {
        return self::hash(array_intersect_key($this->origen, array_flip([
            'codigo', 'grado', 'denominacion', 'nivel', 'area_funcional', 'dependencia',
        ])));
    }

    public function atributos(): array
    {
        $r = $this->origen;

        return ['source_id' => $r['perfil_id'], 'import_key' => $this->identidad(),
            'natural_key' => $this->natural(), 'content_hash' => self::hash($r),
            'denominacion_fuente' => $r['denominacion'], 'dependencia' => $r['dependencia'],
            'area_funcional' => $r['area_funcional'], 'numero_cargos' => $r['numero_cargos'],
            'jefe_inmediato' => $r['jefe_inmediato'], 'proposito_principal' => $r['proposito_principal'],
            'metadata_manual' => $r];
    }

    public function funciones(): array
    {
        return array_map(fn ($f) => ['orden' => $f['orden'], 'descripcion' => $f['texto'],
            'numero_fuente' => $f['numero_fuente'], 'grupo' => $f['grupo']], $this->origen['funciones']);
    }
}
