<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActuacionAdministrativaRequest;
use App\Http\Requests\UpdateActuacionAdministrativaRequest;
use App\Http\Resources\ActuacionAdministrativaResource;
use App\Models\ActuacionAdministrativa;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActuacionAdministrativaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('actuaciones.ver')) {
            return $this->forbiddenResponse();
        }

        $actuaciones = ActuacionAdministrativa::with(['funcionario.cargo', 'creadoPor'])
            ->when($request->filled('funcionario_id'), fn ($q) => $q->where('funcionario_id', $request->funcionario_id))
            ->when($request->filled('tipo_actuacion'), fn ($q) => $q->where('tipo_actuacion', $request->tipo_actuacion))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            ActuacionAdministrativaResource::collection($actuaciones)->response()->getData(true),
            'Actuaciones administrativas consultadas correctamente.'
        );
    }

    public function store(StoreActuacionAdministrativaRequest $request): JsonResponse
    {
        $actuacion = ActuacionAdministrativa::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $this->registrarAuditoria->execute(
            accion: 'crear_actuacion',
            modelo: 'ActuacionAdministrativa',
            modeloId: $actuacion->id,
            descripcion: "Actuacion administrativa creada para funcionario ID {$actuacion->funcionario_id}.",
        );

        return $this->createdResponse(
            new ActuacionAdministrativaResource($actuacion->load('funcionario.cargo', 'creadoPor')),
            'Actuacion administrativa creada correctamente.'
        );
    }

    public function show(Request $request, int $actuacion): JsonResponse
    {
        if (! $request->user()->can('actuaciones.ver')) {
            return $this->forbiddenResponse();
        }

        $model = ActuacionAdministrativa::with(['funcionario.cargo', 'creadoPor'])->findOrFail($actuacion);

        return $this->successResponse(new ActuacionAdministrativaResource($model));
    }

    public function update(UpdateActuacionAdministrativaRequest $request, int $actuacion): JsonResponse
    {
        $model = ActuacionAdministrativa::findOrFail($actuacion);
        $model->update($request->validated());

        $this->registrarAuditoria->execute(
            accion: 'editar_actuacion',
            modelo: 'ActuacionAdministrativa',
            modeloId: $model->id,
            descripcion: "Actuacion administrativa ID {$model->id} actualizada.",
        );

        return $this->successResponse(
            new ActuacionAdministrativaResource($model->fresh('funcionario.cargo', 'creadoPor')),
            'Actuacion administrativa actualizada correctamente.'
        );
    }
}
