<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Domain\Manual\FichaManualData;
use App\Models\ManualCargoVersion;
use App\Models\ManualFuncionVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VerificarPaginasManualService
{
    public function ejecutar(int $versionId, int $actorId, bool $dryRun = false): array
    {
        $actor = User::findOrFail($actorId);
        abort_unless($actor->estado && $actor->can('manual_funciones.editar'), 403);
        $path = database_path('data/manual_paginas_decreto_015_2023_verificadas.json');
        $manifest = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (hash_file('sha256', database_path('data/manual_funciones_decreto_015_2023.json')) !== $manifest['json_sha256']) {
            throw new \DomainException('MANUAL_FUENTE_DIFERENTE');
        }

        return DB::transaction(function () use ($versionId, $actorId, $dryRun, $path, $manifest) {
            $version = ManualFuncionVersion::whereKey($versionId)->lockForUpdate()->firstOrFail();
            $fichas = $version->cargos()->with('funciones')->lockForUpdate()->get()->keyBy('source_id');
            if ($fichas->count() !== count($manifest['fichas']) || $version->acto_numero !== '1000-24/015') {
                throw new \DomainException('MANUAL_VERSION_INCOMPATIBLE_CON_EVIDENCIA');
            }
            $pages = [];
            $updates = [];
            foreach ($manifest['fichas'] as $entry) {
                $ficha = $fichas->get($entry['source_id']);
                $dto = $ficha ? new FichaManualData($ficha->metadata_manual) : null;
                if (! $dto || $entry['content_hash'] !== $ficha->content_hash
                    || $entry['content_hash'] !== FichaManualData::hash($ficha->metadata_manual)
                    || $dto->funciones() !== $ficha->funciones->map(fn ($f) => $f->only(['orden', 'descripcion', 'numero_fuente', 'grupo']))->all()
                    || $entry['funciones_count'] !== $ficha->funciones->count()
                    || $entry['pagina_inicio'] < 1 || $entry['pagina_inicio'] > $manifest['paginas_pdf']
                    || isset($pages[$entry['pagina_inicio']])) {
                    throw new \DomainException('MANUAL_EVIDENCIA_INCOMPATIBLE: '.$entry['source_id']);
                }
                $pages[$entry['pagina_inicio']] = true;
                $updates[] = [$ficha, $entry];
            }
            $changed = 0;
            foreach ($updates as [$ficha, $entry]) {
                if ($ficha->pagina_inicio === $entry['pagina_inicio']
                    && ($ficha->verificacion_documental['manifest_sha256'] ?? null) === hash_file('sha256', $path)) {
                    continue;
                }
                $changed++;
                if (! $dryRun) {
                    $ficha->forceFill(['pagina_inicio' => $entry['pagina_inicio'], 'verificacion_documental' => [
                        'estado' => 'verificada', 'pdf_sha256' => $manifest['pdf_sha256'],
                        'manifest_sha256' => hash_file('sha256', $path), 'content_hash' => $entry['content_hash'],
                        'pagina_pdf' => $entry['pagina_pdf'], 'pagina_fuente_original' => $entry['pagina_fuente_original'],
                        'criterio' => $manifest['criterio'], 'funciones_count' => $entry['funciones_count'],
                        'actor_id' => $actorId, 'verificada_at' => now()->toIso8601String(),
                    ]])->save();
                }
            }
            if (! $dryRun && $changed) {
                app(RegistrarAuditoriaAction::class)->execute('verificar_paginas_manual', 'ManualFuncionVersion', $versionId,
                    'Referencias documentales verificadas; contenido normativo original conservado.',
                    ['actor_id' => $actorId, 'fichas' => $changed, 'manifest_sha256' => hash_file('sha256', $path), 'pdf_sha256' => $manifest['pdf_sha256']], $actorId);
            }

            return ['version_id' => $versionId, 'fichas_verificadas' => count($updates), 'paginas_unicas' => count($pages), 'actualizadas' => $changed, 'dry_run' => $dryRun];
        });
    }
}
