<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class CertificadoPdfService
{
    public function __construct(
        private readonly CertificateDocumentData $documentData,
        private readonly CertificateAssetResolver $assets,
    ) {}

    public function generarDesdeSnapshot(array $snapshot): string
    {
        if (array_key_exists('plantilla_certificado', $snapshot)) {
            return app(CertificateTemplateRenderer::class)->generar($snapshot);
        }
        $dompdf = new Dompdf($this->options());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->renderHtml($snapshot), 'UTF-8');
        $dompdf->render();
        $this->drawPageFurniture($dompdf, $snapshot);

        $pdf = $dompdf->output(['compress' => 1]);
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new \RuntimeException('PDF_GENERATION_FAILED');
        }

        return $pdf;
    }

    public function renderHtml(array $snapshot): string
    {
        if (array_key_exists('plantilla_certificado', $snapshot)) {
            return app(CertificateTemplateRenderer::class)->html($snapshot);
        }

        return view('certificados.laboral', [
            'document' => $this->documentData->fromSnapshot($snapshot),
            'institution' => config('certificate_pdf.institution'),
            'stylesheet' => $this->assets->stylesheet(),
        ])->render();
    }

    private function options(): Options
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('chroot', [
            resource_path('certificate-assets'),
            storage_path('app'),
        ]);
        $options->set('tempDir', storage_path('framework/cache'));
        $options->set('fontCache', storage_path('fonts'));

        return $options;
    }

    private function drawPageFurniture(Dompdf $dompdf, array $snapshot): void
    {
        $institution = config('certificate_pdf.institution');
        $footer = config('certificate_pdf.footer');
        $approvedMark = $this->assets->approvedMark();
        $technicalCode = $this->documentData->fromSnapshot($snapshot)['technical_code'];
        $dompdf->getCanvas()->page_script(static function (
            int $pageNumber,
            int $pageCount,
            $canvas,
            $fontMetrics,
        ) use ($institution, $footer, $approvedMark, $technicalCode): void {
            $regular = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $bold = $fontMetrics->getFont('DejaVu Sans', 'bold');
            $ink = [0.11, 0.24, 0.37];
            $muted = [0.30, 0.35, 0.39];
            $left = $approvedMark ? 112 : 62;
            if ($approvedMark) {
                $canvas->image($approvedMark, 62, 27, 42, 42);
            }
            $canvas->text($left, 31, $institution['entity'], $bold, 9.8, $ink);
            $canvas->text($left, 48, $institution['secretariat'], $regular, 7.3, $muted);
            $canvas->text($left, 62, $institution['office'], $regular, 7.3, $muted);
            if (! $approvedMark) {
                $canvas->text($left, 78, 'ENCABEZADO TIPOGRÁFICO PROVISIONAL', $regular, 6.3, $muted);
            }
            $canvas->rectangle(407, 31, 126, 48, $ink, 0.8);
            $canvas->text(440, 49, 'BORRADOR', $bold, 7.1, $ink);
            $canvas->text(416, 64, 'SIN VALIDEZ OFICIAL', $bold, 6.6, $ink);
            $canvas->line(62, 101, 533, 101, $ink, 0.75);

            $canvas->line(62, 764, 533, 764, $muted, 0.5);
            $canvas->text(62, 780, $footer['entity'], $bold, 6.8, $ink);
            $canvas->text(62, 793, $footer['office'], $regular, 6.6, $muted);
            if ($technicalCode) {
                $canvas->text(62, 806, 'Código técnico del certificado: '.$technicalCode, $regular, 6.5, $muted);
            }
            $canvas->text(351, 783, 'BORRADOR - SIN VALIDEZ OFICIAL', $bold, 6.3, $muted);
            $page = "Página {$pageNumber} de {$pageCount}";
            $canvas->text(533 - $fontMetrics->getTextWidth($page, $regular, 7.2), 815, $page, $regular, 7.2, $muted);
        });
    }
}
