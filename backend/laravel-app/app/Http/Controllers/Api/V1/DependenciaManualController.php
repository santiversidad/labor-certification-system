<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CargoDependenciaResource;
use App\Models\Cargo;
use App\Services\SeleccionarFichaManualService;
use App\Services\UnidadesManualService;
use App\Traits\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DependenciaManualController extends Controller
{
    use ApiResponse;

    private function datos(Request $request): array
    {
        abort_unless($request->user()->can('funcionarios.crear') || $request->user()->can('funcionarios.editar'), 403);

        return $request->validate(['fecha' => ['required', 'date'], 'q' => ['nullable', 'string', 'max:150'],
            'unidad_organizacional_id' => ['sometimes', 'required', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'cargo_id' => ['sometimes', 'required', 'integer', 'exists:cargos,id']]);
    }

    public function index(Request $request, UnidadesManualService $service): JsonResponse
    {
        $data = $this->datos($request);

        return $this->successResponse($service->catalogo(CarbonImmutable::parse($data['fecha']), $data['q'] ?? ''));
    }

    public function cargos(Request $request, UnidadesManualService $service): JsonResponse
    {
        $data = $this->datos($request);
        $request->validate(['unidad_organizacional_id' => ['required'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $fichas = $service->enUnidad(CarbonImmutable::parse($data['fecha']), $data['unidad_organizacional_id']);
        $cargos = Cargo::where('estado', true)->whereIn('id', (clone $fichas)->select('cargo_id'))
            ->select('cargos.id', 'denominacion', 'codigo', 'grado')
            ->selectSub((clone $fichas)->whereColumn('cargo_id', 'cargos.id')->selectRaw('COUNT(*)'), 'perfiles_count')
            ->when($data['q'] ?? '', fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('denominacion', 'ilike', '%'.$term.'%')->orWhere('codigo', 'ilike', '%'.$term.'%')->orWhere('grado', 'ilike', '%'.$term.'%')))
            ->orderBy('denominacion')->orderBy('codigo')->orderBy('grado')
            ->paginate($request->integer('per_page', 100));

        return $this->successResponse(CargoDependenciaResource::collection($cargos)->response()->getData(true));
    }

    public function resolver(Request $request, SeleccionarFichaManualService $service): JsonResponse
    {
        $data = $this->datos($request);
        $request->validate(['unidad_organizacional_id' => ['required'], 'cargo_id' => ['required']]);
        $ficha = $service->porUnidad($data['unidad_organizacional_id'], (int) $data['cargo_id'], CarbonImmutable::parse($data['fecha']));

        return $this->successResponse(['source_id' => $ficha->source_id, 'funciones_count' => $ficha->funciones()->count()]);
    }
}
