<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Models\Cargo;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\ManualFuncionVersion;
use App\Services\ResolverFuncionesFuncionarioService;
use App\Traits\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManualFuncionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function index(IndexQueryRequest $request): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.ver'), 403);

        $manuales = ManualFuncion::query()
            ->with(['versiones' => fn ($query) => $query->orderByDesc('vigencia_desde')])
            ->orderBy($request->validated('orden', 'nombre'), $request->validated('direccion', 'asc'))
            ->paginate($request->integer('per_page', 15));

        return $this->successResponse($manuales);
    }

    public function fichas(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('funcionarios.crear') || $request->user()->can('funcionarios.editar'), 403);
        $datos = $request->validate(['cargo_id' => 'required|integer|exists:cargos,id']);
        $fichas = ManualCargoVersion::with('cargo', 'version')->where('cargo_id', $datos['cargo_id'])
            ->orderBy('area_funcional')->orderBy('source_id')->get();
        $vigentes = app(ResolverFuncionesFuncionarioService::class)
            ->compatibles((int) $datos['cargo_id'], CarbonImmutable::now())->pluck('id');

        return $this->successResponse($fichas->map(fn ($f) => [
            'id' => $f->id, 'source_id' => $f->source_id, 'denominacion' => $f->denominacion_fuente ?? $f->cargo->denominacion,
            'codigo' => $f->cargo->codigo, 'grado' => $f->cargo->grado, 'dependencia' => $f->dependencia,
            'area_funcional' => $f->area_funcional, 'proposito_principal' => $f->proposito_principal,
            'version' => $f->version->version, 'estado' => $f->version->estado, 'vigente' => $vigentes->contains($f->id),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.crear'), 403);
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'unique:manuales_funciones,codigo'],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
        ]);

        $manual = ManualFuncion::create($datos);
        $this->registrarAuditoria->execute(
            accion: 'crear_manual_funciones',
            modelo: 'ManualFuncion',
            modeloId: $manual->id,
            descripcion: "Manual de funciones {$manual->codigo} creado.",
            metadata: ['nuevo' => $manual->getAttributes()],
        );

        return $this->createdResponse($manual, 'Manual de funciones creado correctamente.');
    }

    public function storeVersion(Request $request, ManualFuncion $manual): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.crear'), 403);
        $datos = $this->validarVersion($request, $manual);
        $datos['estado'] = 'borrador';
        $datos['created_by'] = $request->user()->id;

        $version = $manual->versiones()->create($datos);
        $this->registrarAuditoria->execute(
            accion: 'crear_version_manual_funciones',
            modelo: 'ManualFuncionVersion',
            modeloId: $version->id,
            descripcion: "Versión {$version->version} del manual {$manual->codigo} creada.",
            metadata: ['nuevo' => $version->getAttributes()],
        );

        return $this->createdResponse(
            $version,
            'Versión borrador creada correctamente.',
        );
    }

    public function updateVersion(Request $request, ManualFuncionVersion $version): JsonResponse
    {
        return DB::transaction(fn () => $this->updateVersionBloqueada($request,
            ManualFuncionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail()));
    }

    private function updateVersionBloqueada(Request $request, ManualFuncionVersion $version): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.editar'), 403);
        if ($version->estado !== 'borrador') {
            return $this->errorResponse(
                'Una versión publicada o inactiva es inmutable; cree una nueva versión.',
                null,
                409,
                'MANUAL_VERSION_IMMUTABLE',
            );
        }

        $anterior = $version->getAttributes();
        $datos = $this->validarVersion($request, $version->manual, $version);
        $datos['updated_by'] = $request->user()->id;
        $version->update($datos);

        $this->registrarAuditoria->execute(
            accion: 'editar_version_manual_funciones',
            modelo: 'ManualFuncionVersion',
            modeloId: $version->id,
            descripcion: "Versión {$version->version} del manual actualizada.",
            metadata: ['anterior' => $anterior, 'nuevo' => $version->fresh()->getAttributes()],
        );

        return $this->successResponse($version->fresh(), 'Versión actualizada correctamente.');
    }

    public function upsertCargo(
        Request $request,
        ManualFuncionVersion $version,
        Cargo $cargo,
    ): JsonResponse {
        return DB::transaction(fn () => $this->upsertCargoBloqueado($request,
            ManualFuncionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail(), $cargo));
    }

    private function upsertCargoBloqueado(
        Request $request,
        ManualFuncionVersion $version,
        Cargo $cargo,
    ): JsonResponse {
        abort_unless($request->user()->can('manual_funciones.editar'), 403);
        if ($version->estado !== 'borrador') {
            return $this->errorResponse(
                'Una versión publicada o inactiva es inmutable; cree una nueva versión.',
                null,
                409,
                'MANUAL_VERSION_IMMUTABLE',
            );
        }

        // Legacy endpoint must never update an arbitrary profile for a generic position.
        $existentes = $version->cargos()->where('cargo_id', $cargo->id)->get();
        if ($existentes->count() > 1) {
            return $this->errorResponse('Seleccione una ficha específica; este cargo tiene varias.', null, 409, 'MANUAL_FICHA_AMBIGUA');
        }
        if ($existentes->first()?->source_id) {
            return $this->errorResponse('La ficha importada se modifica mediante una fuente revisada y el importador.', null, 409, 'MANUAL_FICHA_IMPORTADA');
        }

        $datos = $request->validate([
            'proposito_principal' => ['required', 'string', 'max:10000'],
            'requisitos' => ['nullable', 'string', 'max:10000'],
            'funciones' => ['required', 'array', 'min:1'],
            'funciones.*.orden' => ['required', 'integer', 'min:1', 'distinct'],
            'funciones.*.descripcion' => ['required', 'string', 'max:10000'],
        ]);

        $anterior = ManualCargoVersion::query()
            ->where('manual_funciones_version_id', $version->id)
            ->where('cargo_id', $cargo->id)
            ->with('funciones')
            ->first()?->toArray();

        $cargoVersion = DB::transaction(function () use ($version, $cargo, $datos) {
            $cargoVersion = ManualCargoVersion::updateOrCreate(
                ['manual_funciones_version_id' => $version->id, 'cargo_id' => $cargo->id],
                [
                    'proposito_principal' => $datos['proposito_principal'],
                    'requisitos' => $datos['requisitos'] ?? null,
                ],
            );
            $cargoVersion->funciones()->delete();
            $cargoVersion->funciones()->createMany($datos['funciones']);

            return $cargoVersion;
        });

        $this->registrarAuditoria->execute(
            accion: 'editar_cargo_manual_funciones',
            modelo: 'ManualCargoVersion',
            modeloId: $cargoVersion->id,
            descripcion: "Contenido del cargo {$cargo->id} actualizado en la versión {$version->version}.",
            metadata: ['anterior' => $anterior, 'nuevo' => $cargoVersion->load('funciones')->toArray()],
        );

        return $this->successResponse(
            $cargoVersion->load('cargo', 'funciones'),
            'Información del cargo actualizada en la versión borrador.',
        );
    }

    public function publicar(Request $request, ManualFuncionVersion $version): JsonResponse
    {
        return DB::transaction(fn () => $this->publicarBloqueada($request,
            ManualFuncionVersion::whereKey($version->id)->lockForUpdate()->firstOrFail()));
    }

    private function publicarBloqueada(Request $request, ManualFuncionVersion $version): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.publicar'), 403);
        if ($version->estado !== 'borrador') {
            return $this->errorResponse('Solo una versión borrador puede publicarse.', null, 409);
        }
        if (! $version->vigencia_desde || ! $version->acto_fecha) {
            return $this->errorResponse('Confirme fechas documentales antes de publicar.', null, 409, 'MANUAL_FECHAS_PENDIENTES');
        }
        if (! empty($version->metadata_manual['pendientes'])) {
            return $this->errorResponse('La conciliación documental del origen sigue pendiente.', null, 409, 'MANUAL_CONCILIACION_PENDIENTE');
        }

        $cargoIds = $version->cargos()->pluck('cargo_id');
        if ($cargoIds->isEmpty()) {
            return $this->errorResponse('La versión debe tener al menos un cargo antes de publicarse.', null, 422);
        }

        $cargosSinFunciones = $version->cargos()
            ->whereDoesntHave('funciones')
            ->exists();
        if ($cargosSinFunciones) {
            return $this->errorResponse(
                'Cada cargo de la versión debe tener al menos una función antes de publicarse.',
                null,
                422,
                'MANUAL_CARGO_WITHOUT_FUNCTIONS',
            );
        }

        $solapada = ManualCargoVersion::query()
            ->whereIn('cargo_id', $cargoIds)
            ->whereHas('version', fn ($query) => $query
                ->where('estado', 'publicado')
                ->whereDate('vigencia_desde', '<=', $version->vigencia_hasta?->toDateString() ?? '9999-12-31')
                ->where(fn ($rango) => $rango
                    ->whereNull('vigencia_hasta')
                    ->orWhereDate('vigencia_hasta', '>=', $version->vigencia_desde->toDateString())))
            ->exists();

        if ($solapada) {
            return $this->errorResponse(
                'La vigencia se solapa con otra versión publicada para al menos uno de sus cargos.',
                null,
                409,
                'MANUAL_VERSION_OVERLAP',
            );
        }

        $version->update(['estado' => 'publicado', 'updated_by' => $request->user()->id]);

        $this->registrarAuditoria->execute(
            accion: 'publicar_manual_funciones',
            modelo: 'ManualFuncionVersion',
            modeloId: $version->id,
            descripcion: "Versión {$version->version} del manual publicada.",
            metadata: ['estado_anterior' => 'borrador', 'estado_nuevo' => 'publicado'],
        );

        return $this->successResponse($version->fresh(), 'Versión publicada correctamente.');
    }

    private function validarVersion(
        Request $request,
        ManualFuncion $manual,
        ?ManualFuncionVersion $version = null,
    ): array {
        return $request->validate([
            'version' => [
                'required', 'string', 'max:80',
                Rule::unique('manual_funciones_versiones', 'version')
                    ->where('manual_funciones_id', $manual->id)
                    ->ignore($version?->id),
            ],
            'vigencia_desde' => ['required', 'date'],
            'vigencia_hasta' => ['nullable', 'date', 'after_or_equal:vigencia_desde'],
            'acto_tipo' => ['required', 'string', 'max:80'],
            'acto_numero' => ['required', 'string', 'max:80'],
            'acto_fecha' => ['required', 'date'],
            'acto_referencia' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
