<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCargoRequest;
use App\Http\Requests\UpdateCargoRequest;
use App\Http\Resources\CargoResource;
use App\Models\Cargo;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargoController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * GET /api/v1/cargos
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('cargos.ver');

        $cargos = Cargo::query()
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->boolean('estado')))
            ->when($request->filled('q'), fn ($q) => $q->where('denominacion', 'ilike', "%{$request->q}%"))
            ->orderBy('denominacion')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            CargoResource::collection($cargos)->response()->getData(true),
            'Cargos consultados correctamente.'
        );
    }

    /**
     * POST /api/v1/cargos
     */
    public function store(StoreCargoRequest $request): JsonResponse
    {
        $cargo = Cargo::create($request->validated());

        $this->registrarAuditoria->execute(
            accion: 'crear',
            modelo: 'Cargo',
            modeloId: $cargo->id,
            descripcion: "Cargo creado: {$cargo->denominacion} ({$cargo->codigo}-{$cargo->grado})",
        );

        return $this->createdResponse(new CargoResource($cargo), 'Cargo creado correctamente.');
    }

    /**
     * GET /api/v1/cargos/{cargo}
     */
    public function show(Request $request, int $cargo): JsonResponse
    {
        $this->authorize('cargos.ver');

        $cargoModel = Cargo::findOrFail($cargo);

        return $this->successResponse(new CargoResource($cargoModel));
    }

    /**
     * PUT /api/v1/cargos/{cargo}
     */
    public function update(UpdateCargoRequest $request, int $cargo): JsonResponse
    {
        $cargoModel = Cargo::findOrFail($cargo);
        $cargoModel->update($request->validated());

        $this->registrarAuditoria->execute(
            accion: 'editar',
            modelo: 'Cargo',
            modeloId: $cargoModel->id,
            descripcion: "Cargo actualizado: {$cargoModel->denominacion}",
        );

        return $this->successResponse(new CargoResource($cargoModel), 'Cargo actualizado correctamente.');
    }
}
