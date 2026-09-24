<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class CertificadoPdfService
{
    public function __construct(
        private readonly CertificateDocumentData $documentData,
    ) {}

    public function generarDesdeSnapshot(array $snapshot): string
    {
        $dompdf = new Dompdf($this->options());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->renderHtml($snapshot), 'UTF-8');
        $dompdf->render();
        $this->drawPageNumbers($dompdf);

        $pdf = $dompdf->output(['compress' => 1]);
        if (! str_starts_with($pdf, '%PDF-')) {
            throw new \RuntimeException('PDF_GENERATION_FAILED');
        }

        return $pdf;
    }

    public function renderHtml(array $snapshot): string
    {
        return view('certificados.laboral', [
            'document' => $this->documentData->fromSnapshot($snapshot),
            'institution' => config('certificate_pdf.institution'),
            'footer' => config('certificate_pdf.footer'),
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

    private function drawPageNumbers(Dompdf $dompdf): void
    {
        $dompdf->getCanvas()->page_script(static function (
            int $pageNumber,
            int $pageCount,
            $canvas,
            $fontMetrics,
        ): void {
            $regular = $fontMetrics->getFont('DejaVu Sans', 'normal');
            $muted = [0.32, 0.35, 0.38];
            $page = "Página {$pageNumber} de {$pageCount}";
            $canvas->text(532 - $fontMetrics->getTextWidth($page, $regular, 7.2), 807, $page, $regular, 7.2, $muted);
        });
    }
}
