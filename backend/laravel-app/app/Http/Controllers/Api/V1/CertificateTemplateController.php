<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlantillaAsset;
use App\Models\PlantillaCertificado;
use App\Models\PlantillaVersion;
use App\Services\CertificateTemplateAssets;
use App\Services\CertificateTemplateDefinition;
use App\Services\CertificateTemplatePreview;
use App\Services\CertificateTemplateRenderer;
use App\Services\CertificateTemplateService;
use App\Services\CertificateTemplateVariables;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CertificateTemplateController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CertificateTemplateService $service) {}

    private function data(Request $request, array $rules = []): array
    {
        $this->service->authorize($request->user());
        if (array_diff(array_keys($request->all()), array_keys($rules))) {
            throw ValidationException::withMessages(['plantilla' => 'La petición contiene atributos no autorizados.']);
        }

        return $request->validate($rules);
    }

    public function index(Request $request)
    {
        $this->data($request);
        $templates = PlantillaCertificado::with(['versiones' => fn ($q) => $q->whereIn('estado', ['borrador', 'publicada'])
            ->select('id', 'plantilla_id', 'numero', 'estado', 'revision', 'sha256', 'updated_at', 'editado_por', 'publicado_at')->with('editor:id,name')])->orderBy('id')->get();

        return $this->successResponse($templates->map(fn ($t) => ['id' => $t->id, 'tipo' => $t->tipo,
            'publicada' => ($v = $t->versiones->firstWhere('id', $t->publicada_id)) ? $this->summary($v) : null,
            'borrador' => ($v = $t->versiones->firstWhere('estado', 'borrador')) ? $this->summary($v) : null]));
    }

    public function catalog(Request $request, CertificateTemplateVariables $variables)
    {
        $data = $this->data($request, ['tipo' => ['required', Rule::in(['sencillo', 'funciones'])]]);

        return $this->successResponse(['variables' => $variables->catalogo($data['tipo']), 'fonts' => CertificateTemplateDefinition::FONTS]);
    }

    public function create(Request $request, int $plantilla)
    {
        $this->data($request);

        return $this->successResponse($this->detail($this->service->createDraft($plantilla, $request->user())), 'Borrador creado.', 201);
    }

    public function history(Request $request, int $plantilla)
    {
        $this->data($request, ['page' => ['sometimes', 'integer', 'min:1']]);
        $template = PlantillaCertificado::findOrFail($plantilla);
        $rows = $template->versiones()->select('id', 'plantilla_id', 'numero', 'estado', 'revision', 'sha256', 'updated_at', 'editado_por', 'publicado_at')->with('editor:id,name')->orderByDesc('numero')->paginate(25);

        return $this->successResponse($rows->through(fn ($v) => $this->summary($v)));
    }

    public function show(Request $request, int $version)
    {
        $this->data($request);

        return $this->successResponse($this->detail(PlantillaVersion::findOrFail($version)));
    }

    public function update(Request $request, int $version)
    {
        $data = $this->data($request, ['revision' => ['required', 'integer', 'min:1'], 'contenido' => ['required', 'array']]);

        return $this->successResponse($this->detail($this->service->save($version, $data['revision'], $data['contenido'], $request->user())), 'Borrador guardado.');
    }

    public function publish(Request $request, int $version)
    {
        $data = $this->data($request, ['revision' => ['required', 'integer', 'min:1'], 'confirmado' => ['required', 'accepted']]);

        return $this->successResponse($this->detail($this->service->publish($version, $data['revision'], $request->user())), 'Versión publicada para certificados nuevos.');
    }

    public function preview(Request $request, int $version, CertificateTemplateDefinition $definition, CertificateTemplatePreview $preview, CertificateTemplateRenderer $renderer)
    {
        $data = $this->data($request, ['contenido' => ['sometimes', 'required', 'array'], 'escenario' => ['required', Rule::in(['breve', 'dos', 'tres', 'extenso'])]]);
        $version = PlantillaVersion::with('plantilla')->findOrFail($version);
        $content = $definition->validar($data['contenido'] ?? $version->contenido, $version->plantilla->tipo);
        $snapshot = $preview->snapshot($version->plantilla->tipo, $data['escenario']);
        $snapshot['plantilla_certificado'] = $this->service->envelope($version, $version->plantilla->tipo, $content);
        $pdf = $renderer->generar($snapshot);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="vista-previa-borrador.pdf"',
            'Cache-Control' => 'private, no-store', 'X-Template-Preview' => 'synthetic']);
    }

    public function upload(Request $request, int $version, CertificateTemplateAssets $assets)
    {
        $data = $this->data($request, ['archivo' => ['required', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp'],
            'posicion' => ['required', Rule::in(['left', 'right'])], 'procedencia' => ['required', 'string', 'max:250']]);
        $draft = PlantillaVersion::findOrFail($version);
        $this->service->editable($draft);
        $asset = $assets->upload($request->file('archivo'), $data['posicion'], $request->user(), $data['procedencia'],
            ['plantilla_id' => $draft->plantilla_id, 'version_id' => $draft->id, 'numero' => $draft->numero]);

        return $this->successResponse($this->assetData($asset), 'Imagen validada y almacenada como recurso inmutable.', 201);
    }

    public function asset(Request $request, int $asset, CertificateTemplateAssets $assets)
    {
        $this->data($request);
        $image = PlantillaAsset::findOrFail($asset);

        return response($assets->bytes($image->getAttributes()), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }

    private function summary(PlantillaVersion $version): array
    {
        return Arr::only($version->attributesToArray(), ['id', 'plantilla_id', 'numero', 'estado', 'revision', 'sha256', 'updated_at', 'publicado_at'])
            + ['ultimo_editor' => $version->editor?->name];
    }

    private function detail(PlantillaVersion $version): array
    {
        $version->loadMissing('plantilla', 'editor:id,name');
        $ids = array_filter($version->contenido['header']);

        return $this->summary($version) + ['tipo' => $version->plantilla->tipo, 'contenido' => $version->contenido,
            'assets' => PlantillaAsset::whereIn('id', $ids)->get()->map(fn ($a) => $this->assetData($a))->values()];
    }

    private function assetData(PlantillaAsset $asset): array
    {
        return Arr::only($asset->attributesToArray(), ['id', 'posicion', 'sha256', 'mime', 'ancho', 'alto', 'bytes', 'procedencia'])
            + ['url' => '/api/v1/admin/plantillas-certificados/assets/'.$asset->id];
    }
}
