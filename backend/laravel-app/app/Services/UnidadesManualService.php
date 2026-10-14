<?php

namespace App\Services;

use App\Models\ManualCargoVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Flat projection of preserved Manual scopes; no inferred institutional hierarchy. */
class UnidadesManualService
{
    public function nombreSql(): string
    {
        return "COALESCE(NULLIF(BTRIM(area_funcional), ''), NULLIF(BTRIM(dependencia), ''))";
    }

    public function normalSql(): string
    {
        $nombre = $this->nombreSql();

        return "LOWER(BTRIM(REGEXP_REPLACE(REGEXP_REPLACE(REPLACE(REPLACE($nombre, '–', '-'), '—', '-'), '\\s*-\\s*', ' - ', 'g'), '\\s+', ' ', 'g')))";
    }

    private function vigentes(CarbonInterface $fecha): Builder
    {
        return ManualCargoVersion::query()->whereHas('version', fn ($q) => $q
            ->where('estado', 'publicado')
            ->where(fn ($v) => $v->where(fn ($b) => $b->where('id', 10)->whereNull('vigencia_desde'))
                ->orWhereDate('vigencia_desde', '<=', $fecha->toDateString()))
            ->where(fn ($v) => $v->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $fecha->toDateString())))
            ->whereHas('cargo', fn ($q) => $q->where('estado', true));
    }

    public function fichas(CarbonInterface $fecha): Builder
    {
        $normal = $this->normalSql();

        return $this->vigentes($fecha)->whereRaw("$normal IS NOT NULL AND $normal NOT IN (?, ?, ?)", ['', 'donde se ubique el cargo', 'todas las dependencias']);
    }

    public function enUnidad(CarbonInterface $fecha, string $clave): Builder
    {
        $normal = $this->normalSql();
        $unidadExiste = $this->fichas($fecha)->whereRaw("MD5($normal) = ?", [$clave])->selectRaw('1');

        // "Todas las dependencias" is explicit source applicability, never a fabricated unit.
        return $this->vigentes($fecha)->whereExists($unidadExiste->toBase())
            ->whereRaw("(MD5($normal) = ? OR $normal = ?)", [$clave, 'todas las dependencias']);
    }

    public function unidadPorClave(?string $clave): ?array
    {
        if (! $clave) {
            return null;
        }
        $normal = $this->normalSql();
        // Preserve a recorded unit even after its normative version has closed.
        $row = DB::table('manual_cargo_versiones')->whereRaw("MD5($normal) = ?", [$clave])
            ->selectRaw("MD5($normal) id, MIN({$this->nombreSql()}) nombre")->groupByRaw($normal)->first();

        return $row ? ['id' => $row->id, 'nombre' => $row->nombre] : null;
    }

    public function catalogo(CarbonInterface $fecha, string $busqueda = '')
    {
        $normal = $this->normalSql();
        $nombre = $this->nombreSql();

        return DB::query()->fromSub($this->fichas($fecha)
            ->selectRaw("MD5($normal) id, $normal normalizacion, MIN($nombre) nombre, COUNT(*) fichas_count")
            ->groupByRaw($normal), 'unidades')
            ->when($busqueda !== '', fn ($q) => $q->where('nombre', 'ilike', '%'.$busqueda.'%'))
            ->orderBy('nombre')->get();
    }

    public function unidadFicha(?int $fichaId): ?array
    {
        if (! $fichaId) {
            return null;
        }
        $normal = $this->normalSql();
        $row = DB::table('manual_cargo_versiones')->where('id', $fichaId)
            ->selectRaw("MD5($normal) id, {$this->nombreSql()} nombre, $normal normalizacion")->first();
        if (! $row || in_array($row->normalizacion, [null, '', 'donde se ubique el cargo', 'todas las dependencias'], true)) {
            return null;
        }

        return ['id' => $row->id, 'nombre' => $row->nombre];
    }
}
