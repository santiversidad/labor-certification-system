<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Models\FuncionarioCargoManualFicha;
use App\Models\ManualActualizacionAsignacion;
use App\Models\ManualCargoLineage;
use App\Models\ManualFuncionVersion;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublicarVersionManualService
{
    public function __construct(
        private readonly PlanificarActualizacionManualService $planificador,
        private readonly RegistrarAuditoriaAction $auditoria,
    ) {}

    public function preflight(ManualFuncionVersion $version): array
    {
        $fichas = $version->cargos()->count();
        $funciones = DB::table('manual_funciones_esenciales as f')
            ->join('manual_cargo_versiones as c', 'c.id', '=', 'f.manual_cargo_version_id')
            ->where('c.manual_funciones_version_id', $version->id)->count();
        $conocimientos = $version->cargos()->get(['metadata_manual'])->sum(
            fn ($ficha) => count($ficha->metadata_manual['conocimientos'] ?? [])
        );
        $incompletas = $version->cargos()->where(function ($q) {
            $q->whereNull('area_funcional')->orWhere('area_funcional', '')
                ->orWhereNull('proposito_principal')->orWhere('proposito_principal', '');
        })->pluck('source_id')->filter()->values()->all();

        return compact('fichas', 'funciones', 'conocimientos', 'incompletas') + [
            'asignaciones' => DB::table('funcionario_cargo')->whereIn('manual_cargo_version_id',
                $version->cargos()->select('id'))->count(),
            'certificados_snapshot' => DB::table('certificados')->whereNotNull('snapshot_datos')->count(),
        ];
    }

    public function publicar(ManualFuncionVersion $version, ?CarbonImmutable $vigenciaDesde, int $actorId,
        bool $adopcionActual = false): array
    {
        return DB::transaction(function () use ($version, $vigenciaDesde, $actorId, $adopcionActual) {
            DB::select('SELECT pg_advisory_xact_lock(2401502023)');
            $version = ManualFuncionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ($version->estado !== 'borrador') {
                throw new DomainException('MANUAL_SOLO_BORRADOR_PUBLICABLE');
            }
            if ($adopcionActual && $version->id !== 10) {
                throw new DomainException('MANUAL_ADOPCION_NO_PERMITIDA');
            }
            if (! $adopcionActual && ! $vigenciaDesde) {
                throw new DomainException('MANUAL_VIGENCIA_DESDE_REQUERIDA');
            }
            if (! $adopcionActual && ! $version->acto_fecha) {
                throw new DomainException('MANUAL_ACTO_FECHA_REQUERIDA');
            }
            if ($version->cargos()->doesntExist() || $version->cargos()->whereDoesntHave('funciones')->exists()) {
                throw new DomainException('MANUAL_CONTENIDO_INCOMPLETO');
            }

            $preflight = $this->preflight($version);
            if (! $adopcionActual && $preflight['incompletas']) {
                throw new DomainException('MANUAL_FICHAS_INCOMPLETAS');
            }
            $actual = ManualFuncionVersion::query()->where('manual_funciones_id', $version->manual_funciones_id)
                ->where('estado', 'publicado')->whereNull('vigencia_hasta')->lockForUpdate()->first();

            $plan = null;
            if ($actual) {
                if (! $vigenciaDesde) {
                    throw new DomainException('MANUAL_VIGENCIA_DESDE_REQUERIDA');
                }
                if ($actual->vigencia_desde && $vigenciaDesde->lte($actual->vigencia_desde)) {
                    throw new DomainException('MANUAL_VIGENCIA_NO_POSTERIOR');
                }
                $solapada = ManualFuncionVersion::query()
                    ->where('manual_funciones_id', $version->manual_funciones_id)
                    ->where('estado', 'publicado')->where('id', '<>', $actual->id)
                    ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $vigenciaDesde->toDateString()))
                    ->exists();
                if ($solapada) {
                    throw new DomainException('MANUAL_VERSION_OVERLAP');
                }
                $plan = $this->planificador->ejecutar($actual, $version);
                $cierre = $vigenciaDesde->subDay();
                $actual->update(['vigencia_hasta' => $cierre, 'updated_by' => $actorId]);
                $this->aplicarAsignaciones($actual, $version, $vigenciaDesde, $actorId);
            } elseif ($adopcionActual && ManualFuncionVersion::query()
                ->where('manual_funciones_id', $version->manual_funciones_id)
                ->where('estado', 'publicado')->exists()) {
                throw new DomainException('MANUAL_VERSION_OVERLAP');
            }

            $metadata = $version->metadata_manual ?? [];
            if ($adopcionActual) {
                $metadata['decision_vigencia'] = [
                    'estado' => 'vigente_confirmado_funcionalmente',
                    'vigencia_desde' => 'no_documentada',
                    'vigencia_hasta_null_significa' => 'vigente_hasta_sustitucion',
                    'confirmada_at' => now()->toIso8601String(),
                ];
            }
            $version->update([
                'estado' => 'publicado', 'vigencia_desde' => $vigenciaDesde?->toDateString(),
                'vigencia_hasta' => null, 'updated_by' => $actorId, 'published_by' => $actorId,
                'published_at' => now(), 'metadata_manual' => $metadata,
            ]);

            $this->auditoria->execute(
                accion: 'publicar_manual_funciones', modelo: 'ManualFuncionVersion', modeloId: $version->id,
                descripcion: "Versión {$version->version} publicada como Manual vigente.",
                metadata: [
                    'usuario_publica' => $actorId, 'fecha_hora' => now()->toIso8601String(),
                    'estado_anterior' => 'borrador', 'estado_nuevo' => 'publicado', 'version' => $version->version,
                    'acto_administrativo' => trim("{$version->acto_tipo} {$version->acto_numero}"),
                    'vigencia_desde' => $vigenciaDesde?->toDateString(), 'vigencia_hasta' => null,
                    'preflight' => $preflight,
                ], actorId: $actorId,
            );

            return ['version' => $version->fresh(), 'preflight' => $preflight, 'plan' => $plan];
        });
    }

    private function aplicarAsignaciones(ManualFuncionVersion $from, ManualFuncionVersion $to,
        CarbonImmutable $fecha, int $actorId): void
    {
        $cierre = $fecha->subDay();
        $planes = ManualActualizacionAsignacion::where('from_version_id', $from->id)
            ->where('to_version_id', $to->id)->get();

        foreach ($planes as $plan) {
            FuncionarioCargoManualFicha::query()->where('funcionario_cargo_id', $plan->funcionario_cargo_id)
                ->whereHas('fichaManual', fn ($q) => $q->where('manual_funciones_version_id', $from->id))
                ->whereNull('vigencia_hasta')->update(['vigencia_hasta' => $cierre, 'updated_at' => now()]);

            if ($plan->clasificacion !== 'AUTO_MIGRABLE' || ! $plan->ficha_candidata_id) {
                $this->auditoria->execute('manual_ficha_no_asignada', 'FuncionarioCargo', $plan->funcionario_cargo_id,
                    'La actualización normativa requiere revisión administrativa; no se aplicó fallback.',
                    ['clasificacion' => $plan->clasificacion, 'to_version_id' => $to->id, 'motivos' => $plan->motivos], $actorId);

                continue;
            }

            $lineage = ManualCargoLineage::where('predecessor_id', $plan->ficha_anterior_id)
                ->where('successor_id', $plan->ficha_candidata_id)->first();
            FuncionarioCargoManualFicha::create([
                'funcionario_cargo_id' => $plan->funcionario_cargo_id,
                'manual_cargo_version_id' => $plan->ficha_candidata_id,
                'vigencia_desde' => $fecha, 'origen' => 'actualizacion_normativa',
                'lineage_id' => $lineage?->id, 'resolved_by' => $actorId, 'resolved_at' => now(),
                'metadata' => ['sin_cambio_laboral' => true, 'version_anterior' => $from->id, 'version_nueva' => $to->id],
            ]);
            $plan->update(['resolved_by' => $actorId, 'resolved_at' => now()]);
        }
    }
}
