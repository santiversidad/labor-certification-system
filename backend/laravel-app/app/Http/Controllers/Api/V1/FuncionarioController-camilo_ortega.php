<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Http\Requests\StoreFuncionarioRequest;
use App\Http\Requests\UpdateFuncionarioRequest;
use App\Http\Resources\FuncionarioResource;
use App\Models\Funcionario;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuncionarioController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * GET /api/v1/funcionarios
     */
    public function index(IndexQueryRequest $request): JsonResponse
    {
        $this->authorize('funcionarios.ver');

        $funcionarios = Funcionario::with('cargo')
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('nombres', 'ilike', "%{$request->q}%")
                        ->orWhere('apellidos', 'ilike', "%{$request->q}%")
                        ->orWhere('numero_documento', 'ilike', "%{$request->q}%");
                });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('dependencia'), fn ($q) => $q->where('dependencia', 'ilike', "%{$request->dependencia}%"))
            ->when($request->filled('cargo_id'), fn ($q) => $q->where('cargo_id', $request->cargo_id))
            ->orderBy($request->validated('orden', 'apellidos'), $request->validated('direccion', 'asc'))
            ->orderBy('nombres')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            FuncionarioResource::collection($funcionarios)->response()->getData(true),
            'Funcionarios consultados correctamente.'
        );
    }

    /**
     * POST /api/v1/funcionarios
     */
    public function store(StoreFuncionarioRequest $request): JsonResponse
    {
        $funcionario = Funcionario::create($request->validated());

        $this->registrarAuditoria->execute(
            accion: 'crear',
            modelo: 'Funcionario',
            modeloId: $funcionario->id,
            descripcion: "Funcionario creado: {$funcionario->nombres} {$funcionario->apellidos} ({$funcionario->numero_documento})",
        );

        return $this->createdResponse(
            new FuncionarioResource($funcionario->load('cargo')),
            'Funcionario creado correctamente.'
        );
    }

    /**
     * GET /api/v1/funcionarios/{funcionario}
     */
    public function show(Request $request, int $funcionario): JsonResponse
    {
        $this->authorize('funcionarios.ver');

        $funcionarioModel = Funcionario::with('cargo', 'user')->findOrFail($funcionario);

        return $this->successResponse(new FuncionarioResource($funcionarioModel));
    }

    /**
     * PUT /api/v1/funcionarios/{funcionario}
     */
    public function update(UpdateFuncionarioRequest $request, int $funcionario): JsonResponse
    {
        $funcionarioModel = Funcionario::findOrFail($funcionario);
        $anterior = $funcionarioModel->getAttributes();
        $funcionarioModel->update($request->validated());

        $this->registrarAuditoria->execute(
            accion: 'editar',
            modelo: 'Funcionario',
            modeloId: $funcionarioModel->id,
            descripcion: "Funcionario actualizado: {$funcionarioModel->nombres} {$funcionarioModel->apellidos}",
            metadata: ['anterior' => $anterior, 'nuevo' => $funcionarioModel->fresh()->getAttributes()],
        );

        return $this->successResponse(
            new FuncionarioResource($funcionarioModel->load('cargo')),
            'Funcionario actualizado correctamente.'
        );
    }

    /**
     * DELETE /api/v1/funcionarios/{funcionario}
     */
    public function destroy(Request $request, int $funcionario): JsonResponse
    {
        $this->authorize('funcionarios.eliminar');

        $funcionarioModel = Funcionario::findOrFail($funcionario);
        $nombre = "{$funcionarioModel->nombres} {$funcionarioModel->apellidos}";

        $relaciones = [
            'solicitudes' => $funcionarioModel->solicitudes()->count(),
            'certificados' => $funcionarioModel->certificados()->count(),
            'actuaciones' => $funcionarioModel->actuaciones()->count(),
            'historial_cargos' => $funcionarioModel->historialCargos()->count(),
            'pagos' => $funcionarioModel->pagosSoportes()->count(),
        ];
        $relacionesExistentes = array_filter($relaciones, fn (int $cantidad) => $cantidad > 0);

        if ($relacionesExistentes !== []) {
            $this->registrarAuditoria->execute(
                accion: 'eliminar_funcionario_bloqueado',
                modelo: 'Funcionario',
                modeloId: $funcionarioModel->id,
                descripcion: "Se bloqueó la eliminación de {$nombre} porque conserva información administrativa relacionada.",
                metadata: ['relaciones' => $relacionesExistentes],
            );

            return $this->errorResponse(
                'El funcionario no puede eliminarse porque tiene información administrativa relacionada.',
                ['relaciones' => array_keys($relacionesExistentes)],
                409,
                'EMPLOYEE_HAS_HISTORY',
            );
        }

        $funcionarioModel->delete();

        $this->registrarAuditoria->execute(
            accion: 'eliminar_funcionario_sin_historial',
            modelo: 'Funcionario',
            modeloId: $funcionario,
            descripcion: "Funcionario eliminado: {$nombre}",
        );

        return $this->successResponse(null, 'Funcionario eliminado correctamente.');
    }
}
