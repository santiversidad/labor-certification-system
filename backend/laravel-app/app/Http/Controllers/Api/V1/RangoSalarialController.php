<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRangoSalarialRequest;
use App\Http\Requests\UpdateRangoSalarialRequest;
use App\Http\Resources\RangoSalarialResource;
use App\Models\RangoSalarial;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RangoSalarialController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * GET /api/v1/rangos-salariales
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('rangos_salariales.ver');

        $rangos = RangoSalarial::query()
            ->when($request->filled('vigencia'), fn ($q) => $q->where('vigencia_anio', $request->integer('vigencia')))
            ->when($request->filled('codigo'), fn ($q) => $q->where('codigo', $request->codigo))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->boolean('estado')))
            ->orderByDesc('vigencia_anio')
            ->orderBy('codigo')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            RangoSalarialResource::collection($rangos)->response()->getData(true),
            'Rangos salariales consultados correctamente.'
        );
    }

    /**
     * POST /api/v1/rangos-salariales
     */
    public function store(StoreRangoSalarialRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $rango = RangoSalarial::create($data);

        $this->registrarAuditoria->execute(
            accion: 'crear',
            modelo: 'RangoSalarial',
            modeloId: $rango->id,
            descripcion: "Rango salarial creado: código {$rango->codigo} grado {$rango->grado} vigencia {$rango->vigencia_anio}",
        );

        return $this->createdResponse(new RangoSalarialResource($rango), 'Rango salarial creado correctamente.');
    }

    /**
     * GET /api/v1/rangos-salariales/{rango_salarial}
     */
    public function show(Request $request, int $rango_salarial): JsonResponse
    {
        $this->authorize('rangos_salariales.ver');

        $rango = RangoSalarial::findOrFail($rango_salarial);

        return $this->successResponse(new RangoSalarialResource($rango));
    }

    /**
     * PUT /api/v1/rangos-salariales/{rango_salarial}
     */
    public function update(UpdateRangoSalarialRequest $request, int $rango_salarial): JsonResponse
    {
        $rango = RangoSalarial::findOrFail($rango_salarial);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        $rango->update($data);

        $this->registrarAuditoria->execute(
            accion: 'editar',
            modelo: 'RangoSalarial',
            modeloId: $rango->id,
            descripcion: "Rango salarial actualizado: código {$rango->codigo} grado {$rango->grado} vigencia {$rango->vigencia_anio}",
        );

        return $this->successResponse(new RangoSalarialResource($rango), 'Rango salarial actualizado correctamente.');
    }

    /**
     * GET /api/v1/rangos-salariales/consultar?codigo=219&grado=02&vigencia=2026
     */
    public function consultar(Request $request): JsonResponse
    {
        $request->validate([
            'codigo'   => ['required', 'string'],
            'grado'    => ['required', 'string'],
            'vigencia' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $rango = RangoSalarial::where('codigo', $request->codigo)
            ->where('grado', $request->grado)
            ->where('vigencia_anio', $request->integer('vigencia'))
            ->where('estado', true)
            ->first();

        if (! $rango) {
            return $this->notFoundResponse(
                "No se encontró rango salarial para código {$request->codigo}, grado {$request->grado}, vigencia {$request->vigencia}."
            );
        }

        return $this->successResponse(new RangoSalarialResource($rango), 'Rango salarial encontrado.');
    }
}
