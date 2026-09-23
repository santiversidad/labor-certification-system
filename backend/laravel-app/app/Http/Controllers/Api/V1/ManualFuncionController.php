<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexQueryRequest;
use App\Models\Cargo;
use App\Models\ManualActualizacionAsignacion;
use App\Models\ManualCargoLineage;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncion;
use App\Models\ManualFuncionVersion;
use App\Services\CompararVersionesManualService;
use App\Services\ImportarBorradorManualService;
use App\Services\PlanificarActualizacionManualService;
use App\Services\PublicarVersionManualService;
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

    public function estado(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.ver'), 403);
        $versiones = ManualFuncionVersion::query()->with('manual')
            ->withCount('cargos')->orderByDesc('published_at')->orderByDesc('id')->get();
        $ids = $versiones->pluck('id');
        $funciones = DB::table('manual_funciones_esenciales as f')
            ->join('manual_cargo_versiones as c', 'c.id', '=', 'f.manual_cargo_version_id')
            ->whereIn('c.manual_funciones_version_id', $ids)
            ->groupBy('c.manual_funciones_version_id')->selectRaw('c.manual_funciones_version_id, COUNT(*) total')
            ->pluck('total', 'manual_funciones_version_id');
        $importaciones = DB::table('manual_importaciones')->whereIn('manual_funciones_version_id', $ids)
            ->groupBy('manual_funciones_version_id')->selectRaw('manual_funciones_version_id, MAX(fecha_importacion) fecha')
            ->pluck('fecha', 'manual_funciones_version_id');
        $actual = $versiones->first(fn ($v) => $v->estado === 'publicado' && ! $v->vigencia_hasta
            && (! $v->vigencia_desde || $v->vigencia_desde->lte(now())));

        return $this->successResponse([
            'manual_vigente' => $actual?->id,
            'versiones' => $versiones->map(fn ($v) => [
                'id' => $v->id, 'manual_id' => $v->manual_funciones_id, 'nombre' => $v->manual->nombre,
                'version' => $v->version, 'acto' => trim("{$v->acto_tipo} {$v->acto_numero}"),
                'estado' => $v->id === $actual?->id ? 'vigente' : $v->estado,
                'estado_dominio' => $v->estado, 'vigencia_desde' => $v->vigencia_desde?->toDateString(),
                'vigencia_hasta' => $v->vigencia_hasta?->toDateString(), 'fichas' => $v->cargos_count,
                'funciones' => (int) ($funciones[$v->id] ?? 0), 'fecha_importacion' => $importaciones[$v->id] ?? null,
                'published_at' => $v->published_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function diff(Request $request, ManualFuncionVersion $from, ManualFuncionVersion $to,
        CompararVersionesManualService $service): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.ver'), 403);

        return $this->successResponse($service->comparar($from, $to));
    }

    public function planificar(Request $request, ManualFuncionVersion $from, ManualFuncionVersion $to,
        PlanificarActualizacionManualService $service): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.editar'), 403);
        $result = $service->ejecutar($from, $to);
        $this->registrarAuditoria->execute('planificar_actualizacion_manual', 'ManualFuncionVersion', $to->id,
            "Actualización normativa {$from->version} → {$to->version} planificada.",
            ['from_version_id' => $from->id, 'conteos' => $result['diff']['conteos'], 'asignaciones' => $result['asignaciones']]);

        return $this->successResponse($result, 'Comparación y mapa de funcionarios preparados.');
    }

    public function importar(Request $request, ManualFuncionVersion $version,
        ImportarBorradorManualService $service): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.editar'), 403);
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:json,xlsx', 'max:20480'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);
        $archivo = $request->file('archivo');
        $result = $service->ejecutar($version, $archivo->getRealPath(), $archivo->getClientOriginalName(),
            (bool) ($data['dry_run'] ?? false), $request->user()->id);

        return $this->successResponse($result, ($data['dry_run'] ?? false) ? 'Dry-run completado.' : 'Borrador importado.');
    }

    public function resolverAsignacion(Request $request, ManualActualizacionAsignacion $actualizacion): JsonResponse
    {
        abort_unless($request->user()->can('manual_funciones.editar'), 403);
        $data = $request->validate(['ficha_candidata_id' => ['required', 'integer', 'exists:manual_cargo_versiones,id']]);

        return DB::transaction(function () use ($request, $actualizacion, $data) {
            $actualizacion = ManualActualizacionAsignacion::whereKey($actualizacion->id)->lockForUpdate()->firstOrFail();
            $candidata = ManualCargoVersion::whereKey($data['ficha_candidata_id'])
                ->where('manual_funciones_version_id', $actualizacion->to_version_id)->firstOrFail();
            $lineage = ManualCargoLineage::updateOrCreate(
                ['predecessor_id' => $actualizacion->ficha_anterior_id, 'successor_id' => $candidata->id],
                ['clasificacion' => 'MODIFICADA', 'estado' => 'confirmado', 'resolved_by' => $request->user()->id,
                    'resolved_at' => now(), 'diferencias' => ['decision' => 'revision_administrativa']],
            );
            $actualizacion->update([
                'ficha_candidata_id' => $candidata->id, 'clasificacion' => 'AUTO_MIGRABLE',
                'motivos' => ['revision_administrativa_resuelta', 'lineage_id' => $lineage->id],
                'resolved_by' => $request->user()->id, 'resolved_at' => now(),
            ]);
            $this->registrarAuditoria->execute('resolver_asignacion_manual', 'ManualActualizacionAsignacion',
                $actualizacion->id, 'Equivalencia normativa resuelta por revisión administrativa.',
                ['ficha_anterior_id' => $actualizacion->ficha_anterior_id, 'ficha_candidata_id' => $candidata->id]);

            return $this->successResponse($actualizacion->fresh(), 'Asignación normativa resuelta.');
        });
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
        abort_unless($request->user()->can('manual_funciones.publicar'), 403);
        if ($version->cargos()->whereDoesntHave('funciones')->exists()) {
            return $this->errorResponse('Cada ficha debe tener al menos una función.', null, 422,
                'MANUAL_CARGO_WITHOUT_FUNCTIONS');
        }
        $data = $request->validate([
            'vigencia_desde' => ['nullable', 'date'],
            'adopcion_actual' => ['sometimes', 'boolean'],
        ]);
        $adopcion = (bool) ($data['adopcion_actual'] ?? false);
        if ($adopcion && $version->id !== 10) {
            return $this->errorResponse('La adopción sin fecha solo aplica a la versión histórica confirmada.', null, 422,
                'MANUAL_ADOPCION_NO_PERMITIDA');
        }
        $result = app(PublicarVersionManualService::class)->publicar(
            $version,
            isset($data['vigencia_desde']) ? CarbonImmutable::parse($data['vigencia_desde'])
                : ($version->vigencia_desde ? CarbonImmutable::parse($version->vigencia_desde) : null),
            $request->user()->id,
            $adopcion,
        );

        return $this->successResponse($result['version'], 'Versión publicada como Manual vigente.');
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
