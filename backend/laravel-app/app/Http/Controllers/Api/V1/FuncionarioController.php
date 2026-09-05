<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoFuncionarioEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\RoleEnum;
use App\Enums\TipoVinculacionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Http\Requests\StoreFuncionarioRequest;
use App\Http\Requests\UpdateFuncionarioRequest;
use App\Http\Resources\FuncionarioResource;
use App\Models\Funcionario;
use App\Models\FuncionarioCargo;
use App\Models\User;
use App\Services\SeleccionarFichaManualService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FuncionarioController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RegistrarAuditoriaAction $registrarAuditoria) {}

    public function index(IndexQueryRequest $request): JsonResponse
    {
        $this->authorize('funcionarios.ver');

        $funcionarios = Funcionario::with(['cargo', 'user'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = (string) $request->validated('q');
                $query->where(function ($sub) use ($term) {
                    $sub->where('nombres', 'ilike', "%{$term}%")
                        ->orWhere('apellidos', 'ilike', "%{$term}%")
                        ->orWhere('numero_documento', 'ilike', "%{$term}%");
                });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->validated('estado')))
            ->when($request->filled('dependencia'), fn ($q) => $q->where('dependencia', 'ilike', '%'.$request->validated('dependencia').'%'))
            ->when($request->filled('cargo_id'), fn ($q) => $q->where('cargo_id', $request->validated('cargo_id')))
            ->orderBy($request->validated('orden', 'apellidos'), $request->validated('direccion', 'asc'))
            ->orderBy('nombres')
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse(
            FuncionarioResource::collection($funcionarios)->response()->getData(true),
            'Funcionarios consultados correctamente.'
        );
    }

    public function store(StoreFuncionarioRequest $request): JsonResponse
    {
        $data = $request->validated();

        $funcionario = DB::transaction(function () use ($data) {
            $ficha = app(SeleccionarFichaManualService::class)->seleccionar((int) $data['cargo_id'], $data['manual_cargo_version_id'] ?? null);
            $estado = EstadoFuncionarioEnum::from($data['estado'] ?? EstadoFuncionarioEnum::Activo->value);
            $nombre = trim($data['nombres'].' '.$data['apellidos']);

            $user = User::create([
                'name' => $nombre,
                'documento' => $data['numero_documento'],
                'password' => Hash::make($data['numero_documento']),
                'telefono' => $data['telefono'] ?? null,
                'email' => $data['correo_institucional'] ?? null,
                'estado' => $estado === EstadoFuncionarioEnum::Activo,
                'must_change_password' => true,
            ]);
            $user->assignRole(RoleEnum::Funcionario->value);

            $funcionario = Funcionario::create(Arr::except($data, ['tipo_vinculacion', 'naturaleza_cargo', 'manual_cargo_version_id']) + ['user_id' => $user->id]);

            FuncionarioCargo::create([
                'funcionario_id' => $funcionario->id,
                'cargo_id' => $data['cargo_id'],
                'manual_cargo_version_id' => $ficha->id,
                'tipo_vinculacion' => $data['tipo_vinculacion'],
                'naturaleza_cargo' => $data['naturaleza_cargo'],
                'es_cargo_base' => true,
                'es_encargo' => $data['tipo_vinculacion'] === TipoVinculacionEnum::Encargo->value,
                'fecha_inicio' => $data['fecha_ingreso'],
            ]);

            return $funcionario;
        });

        $this->registrarAuditoria->execute(
            accion: 'crear_funcionario_con_usuario',
            modelo: 'Funcionario',
            modeloId: $funcionario->id,
            descripcion: "Funcionario y cuenta de primer ingreso creados para {$funcionario->numero_documento}.",
            metadata: ['user_id' => $funcionario->user_id, 'must_change_password' => true, 'cargo_id' => $funcionario->cargo_id],
        );

        return $this->createdResponse(
            new FuncionarioResource($funcionario->load(['cargo', 'user'])),
            'Funcionario y cuenta de acceso creados correctamente.'
        );
    }

    public function show(Request $request, int $funcionario): JsonResponse
    {
        $this->authorize('funcionarios.ver');
        $model = Funcionario::with(['cargo', 'user', 'historialCargos.cargo'])->findOrFail($funcionario);

        return $this->successResponse(new FuncionarioResource($model));
    }

    public function update(UpdateFuncionarioRequest $request, int $funcionario): JsonResponse
    {
        $model = Funcionario::with(['user', 'historialCargos'])->findOrFail($funcionario);
        $data = $request->validated();
        $anterior = $model->getAttributes();

        DB::transaction(function () use ($model, $data) {
            $model = Funcionario::whereKey($model->id)->lockForUpdate()->firstOrFail();
            $cargoAnterior = $model->cargo_id;
            $vigentes = $model->historialCargos()->whereDate('fecha_inicio', '<=', today())->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', today()))->get();
            if ($vigentes->count() > 1) {
                throw ValidationException::withMessages(['cargo_id' => 'ASIGNACION_AMBIGUA']);
            }
            $vigente = $vigentes->first();
            $cargoNuevo = (int) ($data['cargo_id'] ?? $cargoAnterior);
            $ficha = null;
            if (array_key_exists('manual_cargo_version_id', $data) || $cargoNuevo !== $cargoAnterior) {
                $ficha = app(SeleccionarFichaManualService::class)->seleccionar($cargoNuevo, $data['manual_cargo_version_id'] ?? null);
            }
            $model->update(Arr::except($data, ['tipo_vinculacion', 'naturaleza_cargo', 'manual_cargo_version_id']));

            $estado = $model->estado === EstadoFuncionarioEnum::Activo;
            $model->user?->update([
                'name' => trim($model->nombres.' '.$model->apellidos),
                'documento' => $model->numero_documento,
                'telefono' => $model->telefono,
                'email' => $model->correo_institucional,
                'estado' => $estado,
            ]);
            if (! $estado) {
                $model->user?->tokens()->delete();
            }

            if ($ficha && $cargoNuevo === $cargoAnterior && $vigente && ! $vigente->manual_cargo_version_id) {
                $vigente->update(['manual_cargo_version_id' => $ficha->id]);
            } elseif ($cargoNuevo !== $cargoAnterior || ($ficha && $ficha->id !== $vigente?->manual_cargo_version_id)) {
                $fechaInicio = now(config('app.timezone'))->startOfDay();
                if ($vigente && $vigente->fecha_inicio->greaterThanOrEqualTo($fechaInicio)) {
                    throw ValidationException::withMessages(['manual_cargo_version_id' => 'ASIGNACION_CAMBIO_MISMO_DIA: revise el historial antes de sustituir la ficha.']);
                }
                $vigente?->update(['fecha_fin' => $fechaInicio->copy()->subDay()->toDateString()]);

                FuncionarioCargo::create([
                    'funcionario_id' => $model->id,
                    'cargo_id' => $cargoNuevo,
                    'manual_cargo_version_id' => $ficha?->id,
                    'tipo_vinculacion' => $data['tipo_vinculacion'] ?? $vigente?->tipo_vinculacion?->value ?? TipoVinculacionEnum::Planta->value,
                    'naturaleza_cargo' => $data['naturaleza_cargo'] ?? $vigente?->naturaleza_cargo?->value ?? NaturalezaCargoEnum::CarreraAdministrativa->value,
                    'es_cargo_base' => true,
                    'es_encargo' => ($data['tipo_vinculacion'] ?? null) === TipoVinculacionEnum::Encargo->value,
                    'fecha_inicio' => $fechaInicio->toDateString(),
                ]);
            }
        });

        $this->registrarAuditoria->execute(
            accion: 'editar_funcionario',
            modelo: 'Funcionario',
            modeloId: $model->id,
            descripcion: "Funcionario actualizado: {$model->numero_documento}.",
            metadata: ['anterior' => $anterior, 'nuevo' => $model->fresh()->getAttributes()],
        );

        return $this->successResponse(new FuncionarioResource($model->fresh()->load(['cargo', 'user'])), 'Funcionario actualizado correctamente.');
    }

    public function resetAccess(Request $request, int $funcionario): JsonResponse
    {
        $this->authorize('funcionarios.editar');
        $model = Funcionario::with('user')->findOrFail($funcionario);

        if (! $model->user) {
            return $this->errorResponse('El funcionario no tiene una cuenta asociada.', null, 409, 'EMPLOYEE_WITHOUT_USER');
        }

        DB::transaction(function () use ($model) {
            $model->user->update([
                'password' => Hash::make($model->numero_documento),
                'must_change_password' => true,
                'password_changed_at' => null,
            ]);
            $model->user->tokens()->delete();
        });

        $this->registrarAuditoria->execute(
            accion: 'restablecer_acceso_funcionario',
            modelo: 'User',
            modeloId: $model->user_id,
            descripcion: "Acceso restablecido para el funcionario {$model->numero_documento}.",
            metadata: ['must_change_password' => true, 'tokens_revocados' => true],
        );

        return $this->successResponse(null, 'Acceso restablecido. El funcionario deberá cambiar su contraseña al ingresar.');
    }

    public function destroy(Request $request, int $funcionario): JsonResponse
    {
        $this->authorize('funcionarios.eliminar');
        $model = Funcionario::findOrFail($funcionario);
        $relaciones = [
            'solicitudes' => $model->solicitudes()->count(),
            'certificados' => $model->certificados()->count(),
            'actuaciones' => $model->actuaciones()->count(),
            'historial_cargos' => $model->historialCargos()->count(),
            'pagos' => $model->pagosSoportes()->count(),
        ];
        $existentes = array_filter($relaciones, fn (int $cantidad) => $cantidad > 0);

        if ($existentes !== []) {
            $this->registrarAuditoria->execute('eliminar_funcionario_bloqueado', 'Funcionario', $model->id, 'Eliminación bloqueada por conservación del expediente.', ['relaciones' => $existentes]);

            return $this->errorResponse('El funcionario no puede eliminarse porque tiene información administrativa relacionada.', ['relaciones' => array_keys($existentes)], 409, 'EMPLOYEE_HAS_HISTORY');
        }

        $model->delete();
        $this->registrarAuditoria->execute('eliminar_funcionario_sin_historial', 'Funcionario', $funcionario, 'Funcionario sin historial eliminado.');

        return $this->successResponse(null, 'Funcionario eliminado correctamente.');
    }
}
