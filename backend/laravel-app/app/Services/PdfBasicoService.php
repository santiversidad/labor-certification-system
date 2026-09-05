<?php

namespace App\Services;

class PdfBasicoService
{
    public function generarDesdeTexto(string $texto): string
    {
        $lineas = [];
        foreach (preg_split('/\R/', trim($texto)) as $linea) {
            $linea = trim(preg_replace('/\s+/u', ' ', $linea));
            $encoded = iconv('UTF-8', 'Windows-1252', $linea);
            if ($encoded === false) {
                throw new \DomainException('PDF_TEXTO_NO_REPRESENTABLE');
            }
            array_push($lineas, ...explode("\n", wordwrap($encoded, 85, "\n", true)));
        }

        return $this->ensamblarPdf(array_chunk($lineas, 46));
    }

    private function ensamblarPdf(array $paginas): string
    {
        $objetos = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($paginas as $i => $pagina) {
            $pageId = 4 + $i * 2;
            $streamId = $pageId + 1;
            $kids[] = "$pageId 0 R";
            $stream = "BT\n/F1 10 Tf\n50 740 Td\n14 TL\n";
            if ($i > 0) {
                $stream .= '(CERTIFICADO LABORAL TEMPORAL - continuacion) Tj'."\nT*\n";
            }
            foreach ($pagina as $linea) {
                $stream .= '('.$this->escapar($linea).") Tj\nT*\n";
            }
            $stream .= "ET\nBT\n/F1 9 Tf\n50 40 Td\n(Pagina ".($i + 1).' de '.count($paginas).") Tj\nET\n";
            $objetos[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents $streamId 0 R >>";
            $objetos[$streamId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
        }
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($paginas).' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objetos as $id => $objeto) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$objeto\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapar(string $texto): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }
}
