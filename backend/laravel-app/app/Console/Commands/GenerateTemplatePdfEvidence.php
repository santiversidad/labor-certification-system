<?php

namespace App\Console\Commands;

use App\Models\PlantillaCertificado;
use App\Models\User;
use App\Services\CertificateTemplateAssets;
use App\Services\CertificateTemplatePreview;
use App\Services\CertificateTemplateRenderer;
use App\Services\CertificateTemplateService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;
use Spatie\Permission\PermissionRegistrar;

class GenerateTemplatePdfEvidence extends Command
{
    protected $signature = 'certificates:template-evidence {--output=}';

    protected $description = 'Genera evidencia PDF sintética, exclusivamente en bases de prueba aisladas';

    public function handle(CertificateTemplateService $templates, CertificateTemplatePreview $preview, CertificateTemplateRenderer $renderer): int
    {
        if (! app()->environment('testing') || ! preg_match('/_(?:test|e2e)$/', DB::connection()->getDatabaseName())) {
            $this->error('TEMPLATE_EVIDENCE_ISOLATION_REQUIRED');

            return self::FAILURE;
        }
        $output = $this->option('output') ?: storage_path('app/private/template-evidence');
        if (! is_dir($output)) {
            mkdir($output, 0750, true);
        }
        DB::beginTransaction();
        try {
            $this->call('db:seed', ['--class' => RolesPermisosSeeder::class, '--force' => true]);
            $actor = User::factory()->backoffice()->create(['estado' => true]);
            $actor->assignRole('admin');
            $versions = [];
            foreach (['sencillo', 'funciones'] as $type) {
                $versions[$type] = $templates->createDraft(PlantillaCertificado::where('tipo', $type)->sole()->id, $actor);
            }
            $manifest = [];
            foreach (['sencillo-1' => ['sencillo', 1], 'funciones-1' => ['funciones', 1], 'funciones-2' => ['funciones', 2],
                'funciones-3' => ['funciones', 3], 'funciones-5' => ['funciones', 5], 'funcion-extensa' => ['funciones', 0]] as $name => [$type, $target]) {
                $version = $versions[$type];
                $snapshot = $preview->snapshot($type, $target === 1 ? 'breve' : 'extenso');
                $snapshot['plantilla_certificado'] = $templates->envelope($version, $type, $version->contenido);
                if ($name === 'funcion-extensa') {
                    $snapshot['manual_funciones']['funciones'] = [['orden' => 1, 'numero_fuente' => 1, 'grupo' => 'Grupo sintético',
                        'descripcion' => str_repeat('Texto sintético extenso para comprobar continuidad, saltos de página y conservación integral de una sola función. ', 90)]];
                }
                $all = $snapshot['manual_funciones']['funciones'] ?? [];
                for ($n = 1; $n <= 48; $n++) {
                    if ($type === 'funciones' && $target > 1) {
                        $snapshot['manual_funciones']['funciones'] = array_slice($all, 0, $n);
                    }
                    $start = microtime(true);
                    $pdf = $renderer->generar($snapshot);
                    $milliseconds = round((microtime(true) - $start) * 1000, 1);
                    $pages = count((new Parser)->parseContent($pdf)->getPages());
                    if ($pages === $target || $target === 0) {
                        break;
                    }
                    if ($target === 1 || $pages > $target) {
                        throw new \RuntimeException('TEMPLATE_EVIDENCE_PAGE_TARGET: '.$name.'='.$pages);
                    }
                }
                if ($target > 0 && $pages !== $target) {
                    throw new \RuntimeException('TEMPLATE_EVIDENCE_PAGE_TARGET: '.$name);
                }
                file_put_contents($output.'/'.$name.'.pdf', $pdf);
                file_put_contents($output.'/'.$name.'-snapshot.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $manifest[$name] = ['paginas' => $pages, 'funciones' => count($snapshot['manual_funciones']['funciones'] ?? []),
                    'sha256' => hash('sha256', $pdf), 'render_ms' => $milliseconds, 'bytes' => strlen($pdf)];
            }
            $version = $versions['funciones'];
            $config = $version->contenido;
            $config['signature'] = ['name' => 'Firmante Sintético', 'role' => 'Cargo de prueba', 'office' => 'Unidad de prueba', 'text' => 'Configuración sintética de firma.', 'alignment' => 'center'];
            $config['additional_signatures'] = [['label' => 'Revisión de prueba', 'name' => 'Persona Sintética', 'role' => 'Rol de prueba']];
            $version = $templates->save($version->id, $version->revision, $config, $actor);
            $snapshot = $preview->snapshot('funciones', 'breve');
            $snapshot['plantilla_certificado'] = $templates->envelope($version, 'funciones', $version->contenido);
            $pdf = $renderer->generar($snapshot);
            file_put_contents($output.'/firmas-textuales.pdf', $pdf);
            $manifest['firmas-textuales'] = ['paginas' => count((new Parser)->parseContent($pdf)->getPages()), 'sha256' => hash('sha256', $pdf)];
            $image = imagecreatetruecolor(200, 200);
            imagefill($image, 0, 0, imagecolorallocate($image, 20, 48, 79));
            imagestring($image, 5, 48, 90, 'SINTETICO', imagecolorallocate($image, 255, 255, 255));
            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            imagedestroy($image);
            $asset = app(CertificateTemplateAssets::class)->upload(UploadedFile::fake()->createWithContent('logo.png', $png), 'left', $actor, 'Imagen sintética de QA');
            $config = $version->contenido;
            $config['header']['left_asset_id'] = $asset->id;
            $version = $templates->save($version->id, $version->revision, $config, $actor);
            $snapshot['plantilla_certificado'] = $templates->envelope($version, 'funciones', $version->contenido);
            $pdf = $renderer->generar($snapshot);
            file_put_contents($output.'/logo-reemplazado.pdf', $pdf);
            $manifest['logo-reemplazado'] = ['paginas' => count((new Parser)->parseContent($pdf)->getPages()), 'sha256' => hash('sha256', $pdf)];
            file_put_contents($output.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } finally {
            DB::rollBack();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
