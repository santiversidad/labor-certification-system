<?php

namespace App\Services;

use App\Exceptions\CertificateTemplateException;
use Dompdf\Dompdf;
use Dompdf\Options;

class CertificateTemplateRenderer
{
    public function __construct(private readonly CertificateTemplateDefinition $definition, private readonly CertificateTemplateVariables $variables,
        private readonly CertificateTemplateAssets $assets, private readonly CertificateDocumentData $documentData) {}

    public function generar(array $snapshot): string
    {
        $data = $this->data($snapshot);
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('chroot', [resource_path('certificate-assets'), storage_path('app')]);
        $options->set('tempDir', storage_path('framework/cache'));
        $options->set('fontCache', storage_path('fonts'));
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHtml(view('certificados.structured', $data)->render(), 'UTF-8');
        $pdf->render();
        $this->furniture($pdf, $data);

        return $pdf->output(['compress' => 1]);
    }

    public function html(array $snapshot): string
    {
        return view('certificados.structured', $this->data($snapshot))->render();
    }

    private function data(array $snapshot): array
    {
        $envelope = $snapshot['plantilla_certificado'] ?? [];
        if (($envelope['schema_version'] ?? null) !== 1 || ($envelope['tipo'] ?? null) !== ($snapshot['tipo_certificado'] ?? null)
            || ! is_array($envelope['contenido'] ?? null)
            || ! hash_equals((string) ($envelope['sha256'] ?? ''), CertificateTemplateDefinition::hash($envelope['contenido']))) {
            throw new CertificateTemplateException('TEMPLATE_SNAPSHOT_INTEGRITY', 'La plantilla congelada no corresponde al documento o perdió su integridad.');
        }
        $config = $this->definition->validar($envelope['contenido'], $snapshot['tipo_certificado']);
        $images = [];
        foreach (['left', 'right'] as $slot) {
            $id = $config['header'][$slot.'_asset_id'];
            if (! $id) {
                continue;
            }
            $asset = $envelope['assets'][$slot] ?? [];
            if (($asset['id'] ?? null) !== $id || ($asset['mime'] ?? null) !== 'image/png') {
                throw new CertificateTemplateException('TEMPLATE_ASSET_INTEGRITY', 'La imagen congelada no coincide con su posición.');
            }
            $bytes = $this->assets->bytes($asset);
            $dimensions = getimagesizefromstring($bytes);
            $images[$slot] = ['uri' => 'data:image/png;base64,'.base64_encode($bytes), 'width' => $dimensions[0], 'height' => $dimensions[1]];
        }
        $paragraphs = ['body' => [], 'closing' => []];
        foreach ($config['paragraphs'] as $paragraph) {
            $html = '';
            foreach ($paragraph['runs'] as $run) {
                $text = $run['type'] === 'text' ? $run['text'] : $this->variables->resolver($run['variable'], $snapshot);
                if ($text === null) {
                    continue 2; // Optional missing data omits the complete paragraph, never an incomplete sentence.
                }
                $escaped = e($text);
                if ($run['bold'] ?? false) {
                    $escaped = '<strong>'.$escaped.'</strong>';
                }
                if ($run['italic'] ?? false) {
                    $escaped = '<em>'.$escaped.'</em>';
                }
                $html .= $escaped;
            }
            $paragraphs[$paragraph['position']][] = ['alignment' => $paragraph['alignment'], 'html' => $html];
        }
        $functions = [];
        $group = null;
        if ($snapshot['tipo_certificado'] === 'funciones') {
            foreach ($snapshot['manual_funciones']['funciones'] ?? [] as $index => $function) {
                if (! is_string($function['descripcion'] ?? null) || trim($function['descripcion']) === '') {
                    throw new CertificateTemplateException('TEMPLATE_FUNCTIONS_INCOMPLETE', 'El snapshot normativo contiene una función vacía.');
                }
                $newGroup = ($function['grupo'] ?? '') !== $group ? ($function['grupo'] ?? '') : '';
                $group = $function['grupo'] ?? '';
                $functions[] = ['number' => array_key_exists('numero_fuente', $function) ? $function['numero_fuente'] : $index + 1,
                    'description' => $function['descripcion'], 'group' => $newGroup];
            }
            if ($functions === []) {
                throw new CertificateTemplateException('TEMPLATE_FUNCTIONS_INCOMPLETE', 'El certificado con funciones requiere su snapshot normativo.');
            }
        }

        return ['config' => $config, 'snapshot' => $snapshot, 'document' => $this->documentData->fromSnapshot($snapshot),
            'paragraphs' => $paragraphs, 'functions' => $functions, 'images' => $images];
    }

    private function furniture(Dompdf $dompdf, array $data): void
    {
        $footerFont = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $footerLines = $this->footerLines($data['config']['footer'], $footerFont, $dompdf->getFontMetrics());
        $dompdf->getCanvas()->page_script(static function (int $page, int $count, $canvas, $metrics) use ($data, $footerFont, $footerLines): void {
            $font = $metrics->getFont($data['config']['typography']['font'], 'normal');
            $bold = $metrics->getFont($data['config']['typography']['font'], 'bold');
            $ink = [0.12, 0.17, 0.20];
            $muted = [0.36, 0.40, 0.43];
            foreach (['left' => [50, 20, 78, 78], 'right' => [324, 20, 221, 60]] as $slot => [$x, $y, $width, $height]) {
                if (isset($data['images'][$slot])) {
                    $image = $data['images'][$slot];
                    $scale = min($width / $image['width'], $height / $image['height']);
                    $w = $image['width'] * $scale;
                    $h = $image['height'] * $scale;
                    $canvas->image($image['uri'], $x + ($width - $w) / 2, $y + ($height - $h) / 2, $w, $h);
                } else {
                    $canvas->rectangle($x, $y, $width, $height, $muted, 0.5);
                    $canvas->text($x + 4, $y + 20, $slot === 'left' ? 'Escudo pendiente' : 'Identidad institucional pendiente', $font, 6, $muted);
                }
            }
            $canvas->rectangle(507, 87, 38, 38, $muted, 0.5);
            $canvas->text(514, 99, 'QR', $bold, 9, $muted);
            $canvas->text(335, 97, 'Espacio de verificación reservado', $font, 6.3, $muted);
            $canvas->text(335, 109, 'Integración institucional pendiente', $font, 6.3, $muted);
            $canvas->text(50, 132, 'BORRADOR — SIN VALIDEZ OFICIAL', $bold, 8, $ink);
            if ($data['snapshot']['vista_previa_sintetica'] ?? false) {
                $canvas->text(355, 134, 'VISTA PREVIA · DATOS SINTÉTICOS', $font, 6.6, $muted);
            }
            $canvas->line(50, 150, 545, 150, $muted, 0.5);
            $canvas->line(50, 752, 545, 752, $muted, 0.5);
            $y = 762;
            foreach ($footerLines as $line) {
                $canvas->text(50, $y, $line, $footerFont, 6.8, $muted);
                $y += 9;
            }
            $label = "Página {$page} de {$count}";
            $canvas->text(545 - $metrics->getTextWidth($label, $font, 7), 816, $label, $font, 7, $muted);
            $canvas->text(50, 816, 'BORRADOR — SIN VALIDEZ OFICIAL', $bold, 6, $muted);
        });
    }

    private function footerLines(array $f, $font, $metrics): array
    {
        $paragraphs = array_filter([$f['direccion'], implode(' · ', array_filter([
            $f['nit'] ? 'NIT: '.$f['nit'] : '', $f['telefono'] ? 'Teléfono: '.$f['telefono'] : '', $f['codigo_postal'] ? 'Código postal: '.$f['codigo_postal'] : '',
        ])), implode(' · ', array_filter([$f['sitio_web'], $f['cuenta_x'] ? 'X: '.$f['cuenta_x'] : ''])), $f['ciudad']]);
        $lines = [];
        foreach ($paragraphs as $paragraph) {
            $line = '';
            foreach (preg_split('//u', $paragraph, -1, PREG_SPLIT_NO_EMPTY) as $character) {
                if ($line !== '' && $metrics->getTextWidth($line.$character, $font, 6.8) > 490) {
                    $lines[] = $line;
                    $line = '';
                }
                $line .= $character;
            }
            $lines[] = $line;
        }
        if (count($lines) > 6) {
            throw new CertificateTemplateException('TEMPLATE_FOOTER_TOO_LONG', 'El pie ocupa más de seis líneas. Reduzca su contenido antes de publicar.');
        }

        return $lines;
    }
}
