<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Exceptions\CertificateTemplateException;
use App\Models\PlantillaCertificado;
use App\Models\PlantillaVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CertificateTemplateService
{
    public function __construct(private readonly CertificateTemplateDefinition $definition, private readonly CertificateTemplateAssets $assets) {}

    public function authorize(User $actor): void
    {
        abort_unless($actor->estado && $actor->hasRole('admin') && $actor->can('parametros.editar'), 403);
    }

    public function createDraft(int $templateId, User $actor): PlantillaVersion
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($templateId, $actor) {
            $template = PlantillaCertificado::whereKey($templateId)->lockForUpdate()->firstOrFail();
            if ($template->versiones()->where('estado', 'borrador')->exists()) {
                throw new CertificateTemplateException('TEMPLATE_DRAFT_EXISTS', 'Ya existe un borrador. Continúe editando esa versión.');
            }
            $content = $template->publicada?->contenido ?? $this->definition->defaults($template->tipo);
            if (! $template->publicada_id) {
                $content['header'] = $this->assets->reference($actor);
            }
            $version = $template->versiones()->create(['numero' => ($template->versiones()->max('numero') ?? 0) + 1,
                'estado' => 'borrador', 'contenido' => $content, 'sha256' => CertificateTemplateDefinition::hash($content),
                'creado_por' => $actor->id, 'editado_por' => $actor->id]);
            $this->audit('plantilla_creada', $version, $actor, ['header' => array_values($content['header'])]);

            return $version->refresh();
        });
    }

    public function save(int $versionId, int $revision, array $content, User $actor): PlantillaVersion
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($versionId, $revision, $content, $actor) {
            $version = PlantillaVersion::with('plantilla')->whereKey($versionId)->lockForUpdate()->firstOrFail();
            $this->editable($version, $revision);
            $content = $this->definition->validar($content, $version->plantilla->tipo);
            $this->assets->references($content);
            $changed = [];
            foreach ($content as $field => $value) {
                if ($value !== ($version->contenido[$field] ?? null)) {
                    $changed[] = $field;
                }
            }
            $version->update(['contenido' => $content, 'sha256' => CertificateTemplateDefinition::hash($content),
                'revision' => $version->revision + 1, 'editado_por' => $actor->id]);
            $this->audit('plantilla_actualizada', $version, $actor, ['campos' => $changed, 'assets' => array_values($content['header'])]);

            return $version;
        });
    }

    public function publish(int $versionId, int $revision, User $actor): PlantillaVersion
    {
        $this->authorize($actor);
        $templateId = PlantillaVersion::findOrFail($versionId)->plantilla_id;

        return DB::transaction(function () use ($templateId, $versionId, $revision, $actor) {
            $template = PlantillaCertificado::whereKey($templateId)->lockForUpdate()->firstOrFail();
            $version = $template->versiones()->whereKey($versionId)->lockForUpdate()->firstOrFail();
            $this->editable($version, $revision);
            $content = $this->definition->validar($version->contenido, $template->tipo, true);
            $envelope = $this->envelope($version, $template->tipo, $content);
            if (! hash_equals($version->sha256, $envelope['sha256'])) {
                throw new CertificateTemplateException('TEMPLATE_INTEGRITY', 'La huella del borrador no coincide con su contenido.');
            }
            // Preflight is the same renderer and synthetic snapshot as the explicit preview.
            $synthetic = app(CertificateTemplatePreview::class)->snapshot($template->tipo, 'breve');
            $synthetic['plantilla_certificado'] = $envelope;
            app(CertificateTemplateRenderer::class)->generar($synthetic);
            $previousId = $template->publicada_id;
            if ($previousId) {
                $template->publicada()->update(['estado' => 'archivada']);
            }
            $version->update(['estado' => 'publicada', 'publicado_por' => $actor->id, 'publicado_at' => now()]);
            $template->update(['publicada_id' => $version->id]);
            $this->audit('plantilla_publicada', $version, $actor, ['anterior_id' => $previousId, 'assets' => array_values($content['header'])]);

            return $version;
        });
    }

    public function forSnapshot(string $type): ?array
    {
        return DB::transaction(function () use ($type) {
            // Publication takes an exclusive root lock; freeze one coherent version.
            $template = PlantillaCertificado::where('tipo', $type)->sharedLock()->first();
            if (! $template?->publicada) {
                return null; // Explicit legacy contract: no retrospective invented template version.
            }
            $version = $template->publicada;
            if ($version->estado !== 'publicada' || ! hash_equals($version->sha256, CertificateTemplateDefinition::hash($version->contenido))) {
                throw new CertificateTemplateException('TEMPLATE_INTEGRITY', 'No se puede verificar la plantilla publicada.');
            }

            return $this->envelope($version, $type, $version->contenido);
        });
    }

    public function envelope(PlantillaVersion $version, string $type, array $content): array
    {
        return ['schema_version' => 1, 'id' => $version->id, 'numero' => $version->numero, 'tipo' => $type,
            'sha256' => CertificateTemplateDefinition::hash($content), 'contenido' => $content,
            'assets' => $this->assets->references($content)];
    }

    public function editable(PlantillaVersion $version, ?int $revision = null): void
    {
        if ($version->estado !== 'borrador') {
            throw new CertificateTemplateException('TEMPLATE_PUBLISHED_IMMUTABLE', 'Esta versión es inmutable. Cree un nuevo borrador para editarla.');
        }
        if ($revision !== null && $version->revision !== $revision) {
            throw new CertificateTemplateException('TEMPLATE_REVISION_CONFLICT', 'Otro administrador actualizó el borrador. Recargue antes de guardar.');
        }
    }

    private function audit(string $action, PlantillaVersion $version, User $actor, array $metadata): void
    {
        app(RegistrarAuditoriaAction::class)->execute($action, 'PlantillaVersion', $version->id, 'Gestión administrativa de plantilla de certificado.',
            $metadata + ['plantilla_id' => $version->plantilla_id, 'version_id' => $version->id, 'numero' => $version->numero,
                'revision' => $version->revision, 'sha256' => $version->sha256], $actor->id);
    }
}
