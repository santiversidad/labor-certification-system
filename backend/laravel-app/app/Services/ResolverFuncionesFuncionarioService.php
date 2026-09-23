<?php

namespace App\Services;

use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\ManualCargoLineage;
use App\Models\ManualCargoVersion;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class ResolverFuncionesFuncionarioService
{
    public function asignacion(Funcionario $funcionario, CarbonInterface $fecha): FuncionarioCargo
    {
        $asignaciones = $funcionario->historialCargos()->with('cargo')
            ->whereDate('fecha_inicio', '<=', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $fecha->toDateString()))->get();
        if ($asignaciones->isEmpty()) {
            throw new DomainException('ASIGNACION_NO_VIGENTE');
        }
        if ($asignaciones->count() !== 1) {
            throw new DomainException('ASIGNACION_AMBIGUA');
        }
        $asignacion = $asignaciones->sole();
        if (! $asignacion->cargo?->estado) {
            throw new DomainException('CARGO_INVALIDO');
        }

        return $asignacion;
    }

    public function resolver(Funcionario $funcionario, CarbonInterface $fecha): array
    {
        $asignacion = $this->asignacion($funcionario, $fecha);
        $relaciones = $asignacion->fichasNormativas()->with('fichaManual.version')
            ->where(fn ($q) => $q->whereNull('vigencia_desde')->orWhereDate('vigencia_desde', '<=', $fecha->toDateString()))
            ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $fecha->toDateString()))
            ->get();
        if ($relaciones->count() > 1) {
            throw new DomainException('MANUAL_FICHA_AMBIGUA');
        }
        $fichaId = $relaciones->first()?->manual_cargo_version_id;
        $tuvoRelacionesNormativas = $asignacion->fichasNormativas()->exists();

        // Compatibilidad transitoria para asignaciones creadas antes de la tabla normativa.
        // Solo se usa si la ficha legada sigue vigente; nunca permite fallback a una versión cerrada.
        if (! $fichaId && ! $tuvoRelacionesNormativas && $asignacion->manual_cargo_version_id) {
            $fichaId = $asignacion->manual_cargo_version_id;
        }
        if (! $fichaId) {
            // Diagnostic only. Never infer a profile during generation, even with one candidate.
            $count = $this->compatibles($asignacion->cargo_id, $fecha)->count();
            throw new DomainException(match (true) {
                $count > 1 => 'MANUAL_FICHA_AMBIGUA',
                ! $tuvoRelacionesNormativas && $count === 0 => 'MANUAL_FICHA_NO_ENCONTRADA',
                default => 'MANUAL_FICHA_NO_ASIGNADA',
            });
        }
        $ficha = ManualCargoVersion::with('version.manual', 'funciones')->find($fichaId);
        $relacion = $relaciones->first();
        $equivalenciaNormativa = $relacion?->lineage_id && ManualCargoLineage::query()
            ->whereKey($relacion->lineage_id)->where('successor_id', $fichaId)
            ->where('estado', '<>', 'descartado')->exists();
        if (! $ficha || ($ficha->cargo_id !== $asignacion->cargo_id && ! $equivalenciaNormativa)) {
            throw new DomainException('MANUAL_FICHA_INCOMPATIBLE');
        }
        $version = $ficha->version;
        // Only the development command can mark an assignment as a demo. HTTP never accepts this flag.
        $demo = app()->environment(['local', 'testing']) && $asignacion->es_prueba_manual
            && str_starts_with($funcionario->numero_documento, 'TEST-MANUAL-') && $version->estado === 'borrador';
        if (! $demo && ! $this->versionVigente($version, $fecha)) {
            throw new DomainException('MANUAL_VERSION_NO_VIGENTE');
        }
        if (! trim((string) $ficha->area_funcional) || ! trim($ficha->proposito_principal)
            || $ficha->funciones->isEmpty()
            || $ficha->funciones->contains(fn ($f) => ! trim($f->descripcion))) {
            throw new DomainException('MANUAL_FICHA_INCOMPLETA');
        }
        // Level from the preserved source protects against subsequent catalog edits.
        $nivel = $ficha->metadata_manual['nivel'] ?? $asignacion->cargo->nivel;
        $comunes = DB::table('manual_funciones_comunes_nivel')->where('manual_funciones_version_id', $version->id)
            ->where('nivel', $nivel)->orderBy('orden')->get(['orden', 'descripcion'])->map(fn ($f) => (array) $f)->all();
        $especificas = $ficha->funciones->map(fn ($f) => $f->only(['orden', 'descripcion', 'numero_fuente', 'grupo']))->all();

        return [
            'manual_id' => $version->manual->id, 'manual_codigo' => $version->manual->codigo,
            'manual_nombre' => $version->manual->nombre, 'version_id' => $version->id, 'version' => $version->version,
            'ficha_id' => $ficha->id, 'source_id' => $ficha->source_id, 'content_hash' => $ficha->content_hash,
            'asignacion_id' => $asignacion->id, 'cargo_id' => $asignacion->cargo_id,
            'relacion_normativa_id' => $relaciones->first()?->id,
            'relacion_normativa' => $relacion ? [
                'id' => $relacion->id, 'origen' => $relacion->origen, 'lineage_id' => $relacion->lineage_id,
                'vigencia_desde' => $relacion->vigencia_desde?->toDateString(),
                'vigencia_hasta' => $relacion->vigencia_hasta?->toDateString(),
            ] : null,
            'area_funcional' => $ficha->area_funcional, 'dependencia' => $ficha->dependencia,
            'proposito' => $ficha->proposito_principal, 'proposito_principal' => $ficha->proposito_principal,
            'vigencia_desde' => $version->vigencia_desde?->toDateString(),
            'vigencia_hasta' => $version->vigencia_hasta?->toDateString(),
            'acto' => ['tipo' => $version->acto_tipo, 'numero' => $version->acto_numero,
                'fecha' => $version->acto_fecha?->toDateString(), 'referencia' => $version->acto_referencia],
            'funciones' => $especificas, 'funciones_especificas' => $especificas, 'funciones_comunes' => $comunes,
            'funciones_comunes_estado' => $comunes ? 'disponibles' : 'pendientes_de_fuente',
            'imprimir_funciones_comunes' => false, 'prueba_desarrollo' => $demo,
        ];
    }

    public function compatibles(int $cargoId, CarbonInterface $fecha)
    {
        return ManualCargoVersion::where('cargo_id', $cargoId)->whereHas('version', fn ($q) => $q
            ->where('estado', 'publicado')
            ->where(fn ($v) => $v->where(fn ($baseline) => $baseline->where('id', 10)->whereNull('vigencia_desde'))
                ->orWhereDate('vigencia_desde', '<=', $fecha->toDateString()))
            ->where(fn ($v) => $v->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $fecha->toDateString())))
            ->whereNotNull('area_funcional')->where('area_funcional', '<>', '')
            ->where('proposito_principal', '<>', '')->whereHas('funciones');
    }

    public function versionVigente($version, CarbonInterface $fecha): bool
    {
        return $version->estado === 'publicado'
            && (($version->id === 10 && ! $version->vigencia_desde)
                || ($version->vigencia_desde && $version->vigencia_desde->toDateString() <= $fecha->toDateString()))
            && (! $version->vigencia_hasta || $version->vigencia_hasta->toDateString() >= $fecha->toDateString());
    }
}
