<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Domain\SalaryScale\SalaryScaleAmount;
use App\Exceptions\SalaryScaleException;
use App\Models\Cargo;
use App\Models\EscalaSalarialItem;
use App\Models\EscalaSalarialVigencia;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryScaleService
{
    public function __construct(private readonly RegistrarAuditoriaAction $audit) {}

    public function checkRevision(EscalaSalarialVigencia|EscalaSalarialItem $model, int $revision): void
    {
        if ($model->revision !== $revision) {
            throw new SalaryScaleException('SALARY_SCALE_CONFLICT', 'La información cambió. Actualice la tabla y revise nuevamente antes de guardar.');
        }
    }

    public function draft(EscalaSalarialVigencia $year): void
    {
        if ($year->estado !== 'borrador') {
            throw new SalaryScaleException('SALARY_SCALE_READ_ONLY', 'Esta vigencia está publicada y sus valores son de solo lectura. Cree otra vigencia para registrar nuevos valores.');
        }
    }

    private function base(int $id, int $year, int $revision): EscalaSalarialVigencia
    {
        $base = EscalaSalarialVigencia::lockForUpdate()->findOrFail($id);
        $this->checkRevision($base, $revision);
        if ($base->anio >= $year) {
            throw ValidationException::withMessages(['vigencia_base_id' => 'La vigencia base debe ser anterior al año nuevo.']);
        }

        return $base;
    }

    public function preview(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $base = EscalaSalarialVigencia::lockForUpdate()->findOrFail($data['vigencia_base_id']);
            if ($base->anio >= $data['anio']) {
                throw ValidationException::withMessages(['anio' => 'El año nuevo debe ser posterior al año base.']);
            }
            $items = $base->items()->orderBy('id')->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => $item->asignacion_basica_mensual === null)) {
                throw ValidationException::withMessages(['vigencia_base_id' => 'La base debe tener valores completos para calcular el aumento.']);
            }
            $values = $items->map(fn ($item) => ['codigo' => $item->codigo, 'grado' => $item->grado, 'denominacion' => $item->denominacion,
                'valor_base' => $item->asignacion_basica_mensual, 'valor_nuevo' => SalaryScaleAmount::increase($item->asignacion_basica_mensual, $data['porcentaje'])]);

            return ['cantidad' => $items->count(), 'base_revision' => $base->revision, 'ejemplos' => $values->take(5)->values()];
        });
    }

    public function create(array $data, int $actor): EscalaSalarialVigencia
    {
        try {
            return DB::transaction(function () use ($data, $actor) {
                DB::select('SELECT pg_advisory_xact_lock(?)', [73052025]);
                $base = isset($data['vigencia_base_id']) ? $this->base($data['vigencia_base_id'], $data['anio'], $data['base_revision']) : null;
                $percentage = $data['porcentaje'] ?? null;
                if ($percentage !== null && ! $base) {
                    throw ValidationException::withMessages(['vigencia_base_id' => 'Seleccione una base para calcular.']);
                }
                if ($percentage !== null && ! ($data['confirmado'] ?? false)) {
                    throw ValidationException::withMessages(['confirmado' => 'Confirme la generación del borrador después de revisar la vista previa.']);
                }
                $year = EscalaSalarialVigencia::create(['anio' => $data['anio'], 'fuente' => $data['fuente'], 'creado_por' => $actor,
                    'acto_administrativo' => $data['acto_administrativo'] ?? null, 'fecha_vigencia' => $data['fecha_vigencia'] ?? null,
                    'observaciones' => $data['observaciones'] ?? null, 'vigencia_base_id' => $base?->id]);
                $structure = $base ? $base->items()->orderBy('id')->get() : Cargo::where('estado', true)->orderBy('id')->get();
                foreach ($structure as $item) {
                    $year->items()->create(['cargo_id' => $base ? $item->cargo_id : $item->id, 'codigo' => $item->codigo,
                        'grado' => $item->grado, 'denominacion' => $item->denominacion, 'numero_cargos' => $base ? $item->numero_cargos : null]);
                }
                if ($percentage !== null) {
                    $this->calculateLocked($year, $base, $percentage, $actor);
                }
                $this->audit->execute('salary_scale_year_created', 'EscalaSalarialVigencia', $year->id, 'Vigencia salarial creada en borrador.',
                    ['anio' => $year->anio, 'vigencia_base_id' => $base?->id, 'cantidad' => $structure->count()], $actor);

                return $year->fresh()->load('items');
            });
        } catch (QueryException $error) {
            if ($error->getCode() === '23505') {
                throw new SalaryScaleException('SALARY_SCALE_YEAR_EXISTS', 'Ya existe una vigencia para ese año. Actualice la tabla.');
            }
            throw $error;
        }
    }

    private function calculateLocked(EscalaSalarialVigencia $year, EscalaSalarialVigencia $base, string $percentage, int $actor): void
    {
        SalaryScaleAmount::percentage($percentage);
        $items = $year->items()->orderBy('id')->get();
        $source = $base->items()->get()->keyBy(fn ($item) => json_encode([$item->codigo, $item->grado, $item->denominacion]));
        if ($items->isEmpty() || $items->count() !== $source->count()) {
            throw ValidationException::withMessages(['vigencia_base_id' => 'La estructura del borrador debe coincidir con la base.']);
        }
        foreach ($items as $item) {
            if ($item->asignacion_basica_mensual !== null) {
                throw new SalaryScaleException('SALARY_SCALE_ALREADY_CALCULATED', 'El borrador ya contiene valores. El aumento no los sobrescribe; cree otra vigencia o edite cada valor.');
            }
            $original = $source->get(json_encode([$item->codigo, $item->grado, $item->denominacion]));
            if (! $original || $original->asignacion_basica_mensual === null) {
                throw ValidationException::withMessages(['vigencia_base_id' => 'La base debe tener todos los valores compatibles completos.']);
            }
            $item->update(['asignacion_basica_mensual' => SalaryScaleAmount::increase($original->asignacion_basica_mensual, $percentage),
                'origen_valor' => 'calculado_por_porcentaje', 'revision' => $item->revision + 1]);
        }
        $year->update(['vigencia_base_id' => $base->id, 'porcentaje_referencia' => str_replace(',', '.', $percentage), 'revision' => $year->revision + 1]);
        $this->audit->execute('salary_scale_percentage_applied', 'EscalaSalarialVigencia', $year->id, 'Valores calculados en borrador.',
            ['anio' => $year->anio, 'vigencia_base_id' => $base->id, 'porcentaje' => $year->porcentaje_referencia, 'cantidad' => $items->count()], $actor);
    }

    public function calculate(int $id, array $data, int $actor): EscalaSalarialVigencia
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $year = EscalaSalarialVigencia::lockForUpdate()->findOrFail($id);
            $this->draft($year);
            $this->checkRevision($year, $data['revision']);
            $base = $this->base($data['vigencia_base_id'], $year->anio, $data['base_revision']);
            $this->calculateLocked($year, $base, $data['porcentaje'], $actor);

            return $year->fresh()->load('items');
        });
    }

    public function updateItem(int $id, array $data, int $actor): EscalaSalarialItem
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $item = EscalaSalarialItem::findOrFail($id);
            $year = EscalaSalarialVigencia::lockForUpdate()->findOrFail($item->vigencia_id);
            $this->draft($year);
            $item->refresh();
            $this->checkRevision($item, $data['revision']);
            $before = $item->only(['numero_cargos', 'asignacion_basica_mensual', 'origen_valor']);
            $item->update(['numero_cargos' => $data['numero_cargos'], 'asignacion_basica_mensual' => $data['asignacion_basica_mensual'],
                'origen_valor' => $data['asignacion_basica_mensual'] === null ? 'pendiente' : 'manual', 'revision' => $item->revision + 1]);
            $year->increment('revision');
            $this->audit->execute('salary_scale_item_updated', 'EscalaSalarialItem', $item->id, 'Valor de borrador actualizado.',
                ['vigencia_id' => $year->id, 'before' => $before, 'after' => $item->only(array_keys($before))], $actor);

            return $item;
        });
    }

    public function metadata(int $id, array $data, int $actor): EscalaSalarialVigencia
    {
        return DB::transaction(function () use ($id, $data, $actor) {
            $year = EscalaSalarialVigencia::lockForUpdate()->findOrFail($id);
            $this->checkRevision($year, $data['revision']);
            $fields = ['fuente', 'acto_administrativo', 'fecha_vigencia', 'observaciones'];
            $before = $year->only($fields);
            $year->fill(array_intersect_key($data, array_flip($fields)));
            $year->revision++;
            $year->save();
            $this->audit->execute('salary_scale_year_metadata_updated', 'EscalaSalarialVigencia', $year->id, 'Procedencia de la vigencia actualizada.',
                ['before' => $before, 'after' => $year->only($fields)], $actor);

            return $year;
        });
    }

    public function publish(int $id, int $revision, int $actor): EscalaSalarialVigencia
    {
        return DB::transaction(function () use ($id, $revision, $actor) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [73052026]);
            $year = EscalaSalarialVigencia::lockForUpdate()->findOrFail($id);
            $this->draft($year);
            $this->checkRevision($year, $revision);
            if (! $year->items()->exists() || $year->items()->whereNull('asignacion_basica_mensual')->exists()) {
                throw ValidationException::withMessages(['vigencia' => 'Complete todas las asignaciones básicas antes de publicar.']);
            }
            $later = EscalaSalarialVigencia::where('estado', 'vigente')->where('anio', '>', $year->anio)->exists();
            EscalaSalarialVigencia::where('estado', 'vigente')->where('anio', '<', $year->anio)->update(['estado' => 'archivada', 'revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
            $year->update(['estado' => $later ? 'archivada' : 'vigente', 'publicado_por' => $actor, 'publicado_at' => now(), 'revision' => $year->revision + 1]);
            $this->audit->execute('salary_scale_year_published', 'EscalaSalarialVigencia', $year->id, 'Vigencia publicada como referencia operativa; no acredita acto oficial.',
                ['anio' => $year->anio, 'estado' => $year->estado, 'cantidad' => $year->items()->count(), 'acto_registrado' => filled($year->acto_administrativo)], $actor);

            return $year;
        });
    }
}
