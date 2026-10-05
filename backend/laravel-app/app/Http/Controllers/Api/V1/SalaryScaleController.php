<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\SalaryScale\SalaryScaleAmount;
use App\Http\Controllers\Controller;
use App\Models\EscalaSalarialItem;
use App\Models\EscalaSalarialVigencia;
use App\Services\SalaryScaleService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryScaleController extends Controller
{
    use ApiResponse;

    private function authorized(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin') && $request->user()->can('escala_salarial.gestionar'), 403);
    }

    private function data(Request $request, array $rules): array
    {
        $this->authorized($request);
        $unknown = array_diff(array_keys($request->all()), array_keys($rules));
        if ($unknown) {
            throw ValidationException::withMessages(array_fill_keys($unknown, 'Este campo no se admite en la escala salarial.'));
        }

        return $request->validate($rules);
    }

    private function metadataRules(): array
    {
        return ['fuente' => ['required', 'string', 'max:250'], 'acto_administrativo' => ['nullable', 'string', 'max:250'],
            'fecha_vigencia' => ['nullable', 'date_format:Y-m-d'], 'observaciones' => ['nullable', 'string', 'max:2000']];
    }

    private function baseRules(): array
    {
        return ['vigencia_base_id' => ['nullable', 'integer', 'exists:escala_salarial_vigencias,id'],
            'base_revision' => ['required_with:vigencia_base_id', 'integer', 'min:1'],
            'porcentaje' => ['nullable', 'string', 'max:6'], 'confirmado' => ['sometimes', 'accepted']];
    }

    private function yearPayload(EscalaSalarialVigencia $year): array
    {
        return Arr::only($year->attributesToArray(), ['id', 'anio', 'estado', 'porcentaje_referencia', 'vigencia_base_id', 'acto_administrativo',
            'fecha_vigencia', 'fuente', 'observaciones', 'revision', 'creado_por', 'publicado_por', 'publicado_at']);
    }

    public function years(Request $request): JsonResponse
    {
        $this->authorized($request);

        return $this->successResponse(EscalaSalarialVigencia::orderBy('anio')->get()->map(fn ($year) => $this->yearPayload($year)));
    }

    public function index(Request $request): JsonResponse
    {
        $data = $this->data($request, ['q' => ['nullable', 'string', 'max:150'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100']]);
        $sourceRows = EscalaSalarialItem::select('codigo', 'grado', 'denominacion')->distinct()
            ->when($data['q'] ?? '', fn ($query, $term) => $query->where(fn ($q) => $q->where('codigo', 'ilike', '%'.$term.'%')->orWhere('grado', 'ilike', '%'.$term.'%')->orWhere('denominacion', 'ilike', '%'.$term.'%')));
        // Count source combinations, not annual cells, for accurate pagination.
        $rows = DB::query()->fromSub($sourceRows, 'salary_rows')
            ->orderBy('codigo')->orderBy('grado')->orderBy('denominacion')->paginate($request->integer('per_page', 50));
        $items = $rows->isEmpty() ? collect() : EscalaSalarialItem::where(function ($query) use ($rows) {
            foreach ($rows as $row) {
                $query->orWhere(fn ($q) => $q->where('codigo', $row->codigo)->where('grado', $row->grado)->where('denominacion', $row->denominacion));
            }
        })->get();

        return $this->successResponse(['vigencias' => EscalaSalarialVigencia::orderBy('anio')->get()->map(fn ($year) => $this->yearPayload($year)),
            'filas' => $rows->getCollection()->map(fn ($row) => ['codigo' => $row->codigo, 'grado' => $row->grado, 'denominacion' => $row->denominacion,
                'valores' => $items->filter(fn ($item) => $item->codigo === $row->codigo && $item->grado === $row->grado && $item->denominacion === $row->denominacion)->values()]),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]]);
    }

    public function show(Request $request, int $vigencia): JsonResponse
    {
        $this->authorized($request);
        $year = EscalaSalarialVigencia::with('items')->findOrFail($vigencia);

        return $this->successResponse($this->yearPayload($year) + ['items' => $year->items]);
    }

    public function store(Request $request, SalaryScaleService $service): JsonResponse
    {
        $data = $this->data($request, ['anio' => ['required', 'integer', 'between:1900,2199']] + $this->metadataRules() + $this->baseRules());
        if (isset($data['porcentaje'])) {
            SalaryScaleAmount::percentage($data['porcentaje']);
        }

        return $this->createdResponse($this->yearPayload($service->create($data, $request->user()->id)), 'Vigencia creada en borrador.');
    }

    public function preview(Request $request, SalaryScaleService $service): JsonResponse
    {
        $data = $this->data($request, ['anio' => ['required', 'integer', 'between:1900,2199'], 'vigencia_base_id' => ['required', 'integer', 'exists:escala_salarial_vigencias,id'], 'porcentaje' => ['required', 'string', 'max:6']]);
        SalaryScaleAmount::percentage($data['porcentaje']);

        return $this->successResponse($service->preview($data));
    }

    public function calculate(Request $request, int $vigencia, SalaryScaleService $service): JsonResponse
    {
        $data = $this->data($request, ['revision' => ['required', 'integer', 'min:1'], 'vigencia_base_id' => ['required', 'integer', 'exists:escala_salarial_vigencias,id'],
            'base_revision' => ['required', 'integer', 'min:1'], 'porcentaje' => ['required', 'string', 'max:6'], 'confirmado' => ['required', 'accepted']]);

        return $this->successResponse($this->yearPayload($service->calculate($vigencia, $data, $request->user()->id)), 'Borrador calculado.');
    }

    public function update(Request $request, int $vigencia, SalaryScaleService $service): JsonResponse
    {
        $data = $this->data($request, ['revision' => ['required', 'integer', 'min:1']] + $this->metadataRules());

        return $this->successResponse($this->yearPayload($service->metadata($vigencia, $data, $request->user()->id)), 'Procedencia actualizada.');
    }

    public function item(Request $request, int $item, SalaryScaleService $service): JsonResponse
    {
        $data = $this->data($request, ['revision' => ['required', 'integer', 'min:1'],
            'numero_cargos' => ['present', 'nullable', 'integer', 'between:0,1000000'],
            'asignacion_basica_mensual' => ['present', 'nullable', 'regex:/^[1-9]\d{0,11}$/D']]);

        return $this->successResponse($service->updateItem($item, $data, $request->user()->id), 'Valor actualizado.');
    }

    public function publish(Request $request, int $vigencia, SalaryScaleService $service): JsonResponse
    {
        $data = $this->data($request, ['revision' => ['required', 'integer', 'min:1'], 'confirmado' => ['required', 'accepted']]);

        return $this->successResponse($this->yearPayload($service->publish($vigencia, $data['revision'], $request->user()->id)), 'Vigencia publicada como referencia operativa.');
    }
}
