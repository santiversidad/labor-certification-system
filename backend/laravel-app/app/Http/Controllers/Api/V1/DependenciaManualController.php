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
            'perfiles' => ['sometimes', 'boolean'], 'funciones' => ['sometimes', 'boolean'],
            'manual_cargo_version_id' => ['sometimes', 'required', 'integer', 'exists:manual_cargo_versiones,id'],
            'pagina_inicio' => ['prohibited'], 'pagina_pdf' => ['prohibited'],
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
        if ($request->boolean('perfiles')) {
            $rows = $fichas->with(['cargo:id,denominacion,codigo,grado', 'version:id,version'])
                ->withCount('funciones')
                ->when($data['q'] ?? '', fn ($query, $term) => $query->where(fn ($q) => $q
                    ->whereHas('cargo', fn ($c) => $c->where('denominacion', 'ilike', '%'.$term.'%')->orWhere('codigo', 'ilike', '%'.$term.'%')->orWhere('grado', 'ilike', '%'.$term.'%'))
                    ->orWhereRaw('CAST(pagina_inicio AS TEXT) ILIKE ?', ['%'.$term.'%'])))
                ->orderBy('cargo_id')->orderBy('pagina_inicio')->paginate($request->integer('per_page', 100));

            return $this->successResponse($rows->through(fn ($f) => $this->resumen($f)));
        }
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
        $ficha = $service->porUnidad($data['unidad_organizacional_id'], (int) $data['cargo_id'], CarbonImmutable::parse($data['fecha']), $data['manual_cargo_version_id'] ?? null);

        if (isset($data['manual_cargo_version_id'])) {
            $ficha->loadMissing('cargo', 'version')->loadCount('funciones');
            $result = $this->resumen($ficha);
            $result['dependencia_seleccionada'] = app(UnidadesManualService::class)->unidadPorClave($data['unidad_organizacional_id']);
            if ($request->boolean('funciones')) {
                $result['funciones'] = $ficha->funciones()->get(['orden', 'descripcion', 'numero_fuente', 'grupo'])->toArray();
            }

            return $this->successResponse($result);
        }

        return $this->successResponse(['source_id' => $ficha->source_id, 'funciones_count' => $ficha->funciones()->count()]);
    }

    private function resumen($ficha): array
    {
        return ['id' => $ficha->cargo_id, 'manual_cargo_version_id' => $ficha->id,
            'source_id' => $ficha->source_id, 'denominacion' => $ficha->cargo->denominacion,
            'codigo' => $ficha->cargo->codigo, 'grado' => $ficha->cargo->grado,
            'pagina_inicio' => $ficha->pagina_inicio, 'pagina_verificada' => $ficha->paginaVerificada(),
            'area_funcional' => $ficha->area_funcional, 'dependencia' => $ficha->dependencia,
            'transversal' => mb_strtolower(trim((string) $ficha->area_funcional)) === 'todas las dependencias',
            'version_id' => $ficha->manual_funciones_version_id, 'version' => $ficha->version->version,
            'funciones_count' => $ficha->funciones_count,
            'seleccionable' => $ficha->paginaVerificada() && trim((string) $ficha->area_funcional) !== '' && trim((string) $ficha->proposito_principal) !== '' && $ficha->funciones_count > 0,
            'perfiles_count' => 1];
    }

    public function funcionesRegistradas(Request $request, int $ficha): JsonResponse
    {
        abort_unless($request->user()->can('funcionarios.editar') || $request->user()->can('funcionarios.crear'), 403);
        $data = $request->validate(['funcionario_id' => ['required', 'integer', 'exists:funcionarios,id']]);
        $asignaciones = \App\Models\FuncionarioCargo::where('funcionario_id', $data['funcionario_id'])
            ->where(fn ($q) => $q->whereHas('fichasNormativas', fn ($n) => $n->where('manual_cargo_version_id', $ficha))
                ->orWhere(fn ($q) => $q->whereDoesntHave('fichasNormativas')->where('manual_cargo_version_id', $ficha)));
        abort_unless($asignaciones->exists(), 404);
        $perfil = \App\Models\ManualCargoVersion::with('cargo', 'version')->withCount('funciones')->findOrFail($ficha);

        return $this->successResponse($this->resumen($perfil) + [
            'funciones' => $perfil->funciones()->get(['orden', 'descripcion', 'numero_fuente', 'grupo'])->toArray(),
        ]);
    }
}
