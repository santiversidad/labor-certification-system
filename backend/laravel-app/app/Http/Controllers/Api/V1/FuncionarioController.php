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
            $ficha = app(SeleccionarFichaManualService::class)->porUnidad($data['unidad_organizacional_id'], (int) $data['cargo_id'], \Carbon\CarbonImmutable::parse($data['fecha_ingreso']), $data['manual_cargo_version_id'] ?? null);
            $data['dependencia'] = app(\App\Services\UnidadesManualService::class)->unidadPorClave($data['unidad_organizacional_id'])['nombre'];
            $estado = EstadoFuncionarioEnum::from($data['estado'] ?? EstadoFuncionarioEnum::Activo->value);
            $nombre = trim($data['nombres'].' '.$data['apellidos']);

            $user = User::create([
                'name' => $nombre, 'tipo_cuenta' => 'funcionario',
                'documento' => $data['numero_documento'],
                'password' => Hash::make($data['numero_documento']),
                'telefono' => $data['telefono'] ?? null,
                'email' => $data['correo_institucional'] ?? null,
                'estado' => $estado === EstadoFuncionarioEnum::Activo,
                'must_change_password' => true,
            ]);
            $user->assignRole(RoleEnum::Funcionario->value);

            $funcionario = Funcionario::create(Arr::except($data, ['tipo_vinculacion', 'naturaleza_cargo', 'manual_cargo_version_id', 'unidad_organizacional_id']) + ['user_id' => $user->id]);

            $asignacion = FuncionarioCargo::create([
                'funcionario_id' => $funcionario->id,
                'unidad_organizacional_id' => $data['unidad_organizacional_id'],
                'cargo_id' => $data['cargo_id'],
                'manual_cargo_version_id' => $ficha->id,
                'tipo_vinculacion' => $data['tipo_vinculacion'],
                'naturaleza_cargo' => $data['naturaleza_cargo'],
                'es_cargo_base' => true,
                'es_encargo' => $data['tipo_vinculacion'] === TipoVinculacionEnum::Encargo->value,
                'fecha_inicio' => $data['fecha_ingreso'],
            ]);
            $asignacion->fichasNormativas()->create([
                'manual_cargo_version_id' => $ficha->id,
                'vigencia_desde' => $data['fecha_ingreso'],
                'origen' => 'asignacion_inicial',
                'resolved_by' => request()->user()?->getKey(), 'resolved_at' => now(),
                'metadata' => ['version_id' => $ficha->manual_funciones_version_id, 'pagina_inicio' => $ficha->pagina_inicio, 'source_id' => $ficha->source_id],
            ]);

            $this->registrarAuditoria->execute(
                accion: 'crear_funcionario_con_usuario',
                modelo: 'Funcionario',
                modeloId: $funcionario->id,
                descripcion: "Funcionario y cuenta de primer ingreso creados para {$funcionario->numero_documento}.",
                metadata: ['user_id' => $funcionario->user_id, 'must_change_password' => true, 'cargo_id' => $funcionario->cargo_id,
                    'funcionario_cargo_id' => $asignacion->id, 'ficha_id' => $ficha->id, 'version_id' => $ficha->manual_funciones_version_id, 'pagina_inicio' => $ficha->pagina_inicio],
            );
            $this->registrarAuditoria->execute(
                accion: 'crear_usuario_funcionario', modelo: 'User', modeloId: $user->id,
                descripcion: 'Cuenta institucional creada con cambio obligatorio de contraseña.',
                metadata: ['funcionario_id' => $funcionario->id, 'must_change_password' => true],
            );

            return $funcionario;
        });

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
        // Empty prohibited values may pass Laravel validation; never persist client dependency text.
        $data = Arr::except($request->validated(), ['dependencia']);
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
            $relaciones = $vigente?->fichasNormativas()
                ->where(fn ($q) => $q->whereNull('vigencia_desde')->orWhereDate('vigencia_desde', '<=', today()))
                ->where(fn ($q) => $q->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', today()))->get();
            $fichaActualId = $relaciones?->count() === 1 ? $relaciones->sole()->manual_cargo_version_id
                : ($relaciones?->isEmpty() && ! $vigente?->fichasNormativas()->exists() ? $vigente?->manual_cargo_version_id : null);
            $unidadActual = $vigente?->unidad_organizacional_id ?? app(\App\Services\UnidadesManualService::class)->unidadFicha($fichaActualId)['id'] ?? null;
            $cambioUnidad = isset($data['unidad_organizacional_id']) && $data['unidad_organizacional_id'] !== $unidadActual;
            $ficha = null;
            if ($cambioUnidad || $cargoNuevo !== $cargoAnterior || (isset($data['unidad_organizacional_id']) && ! $fichaActualId)
                || (isset($data['manual_cargo_version_id']) && $data['manual_cargo_version_id'] !== $fichaActualId)) {
                if (! isset($data['unidad_organizacional_id'])) {
                    throw ValidationException::withMessages([isset($data['manual_cargo_version_id']) ? 'manual_cargo_version_id' : 'unidad_organizacional_id' => 'Seleccione una dependencia para modificar la vinculación.']);
                }
                $ficha = app(SeleccionarFichaManualService::class)->porUnidad($data['unidad_organizacional_id'], $cargoNuevo, \Carbon\CarbonImmutable::today(), $data['manual_cargo_version_id'] ?? null);
                $data['dependencia'] = app(\App\Services\UnidadesManualService::class)->unidadPorClave($data['unidad_organizacional_id'])['nombre'];
            }
            $model->update(Arr::except($data, ['tipo_vinculacion', 'naturaleza_cargo', 'manual_cargo_version_id', 'unidad_organizacional_id']));

            $estado = $model->estado === EstadoFuncionarioEnum::Activo;
            $model->user?->update([
                'name' => trim($model->nombres.' '.$model->apellidos),
                'documento' => $model->numero_documento,
                'telefono' => $model->telefono,
                'email' => $model->correo_institucional,
                'estado' => $estado,
            ]);
            if (! $estado) {
                $model->user?->revokeAuthentication();
            }

            if ($cambioUnidad || $cargoNuevo !== $cargoAnterior || ($ficha && $ficha->id !== $fichaActualId)) {
                $fechaInicio = now(config('app.timezone'))->startOfDay();
                if ($vigente && $vigente->fecha_inicio->greaterThanOrEqualTo($fechaInicio)) {
                    throw ValidationException::withMessages(['manual_cargo_version_id' => 'ASIGNACION_CAMBIO_MISMO_DIA: revise el historial antes de sustituir la ficha.']);
                }
                $vigente?->update(['fecha_fin' => $fechaInicio->copy()->subDay()->toDateString()]);

                $nuevaAsignacion = FuncionarioCargo::create([
                    'funcionario_id' => $model->id,
                    'unidad_organizacional_id' => $data['unidad_organizacional_id'],
                    'cargo_id' => $cargoNuevo,
                    'manual_cargo_version_id' => $ficha?->id,
                    'tipo_vinculacion' => $data['tipo_vinculacion'] ?? $vigente?->tipo_vinculacion?->value ?? TipoVinculacionEnum::Planta->value,
                    'naturaleza_cargo' => $data['naturaleza_cargo'] ?? $vigente?->naturaleza_cargo?->value ?? NaturalezaCargoEnum::CarreraAdministrativa->value,
                    'es_cargo_base' => true,
                    'es_encargo' => ($data['tipo_vinculacion'] ?? null) === TipoVinculacionEnum::Encargo->value,
                    'fecha_inicio' => $fechaInicio->toDateString(),
                ]);
                $nuevaAsignacion->fichasNormativas()->create([
                    'manual_cargo_version_id' => $ficha->id, 'vigencia_desde' => $fechaInicio,
                    'origen' => 'asignacion_inicial', 'resolved_by' => request()->user()?->getKey(), 'resolved_at' => now(),
                    'metadata' => ['version_id' => $ficha->manual_funciones_version_id, 'pagina_inicio' => $ficha->pagina_inicio, 'source_id' => $ficha->source_id],
                ]);
                $this->registrarAuditoria->execute('seleccionar_ficha_manual', 'FuncionarioCargo', $nuevaAsignacion->id,
                    'Selección administrativa explícita de perfil normativo.',
                    ['ficha_anterior_id' => $fichaActualId, 'ficha_id' => $ficha->id, 'version_id' => $ficha->manual_funciones_version_id, 'pagina_inicio' => $ficha->pagina_inicio]);
            } elseif ($vigente) {
                $vigente->update(Arr::only($data, ['tipo_vinculacion', 'naturaleza_cargo']));
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
            $model->user->revokeAuthentication();
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

        return $this->errorResponse('La eliminación está deshabilitada. Inactive al funcionario conservando su cuenta y expediente.', null, 410, 'EMPLOYEE_DELETE_DISABLED');
    }
}
