<?php

namespace App\Services;

class PdfBasicoService
{
    public function generarDesdeTexto(string $texto): string
    {
        $lineas = preg_split('/\R+/', trim($texto)) ?: [];
        $contenido = "BT\n/F1 11 Tf\n50 780 Td\n14 TL\n";

        foreach ($lineas as $linea) {
            $linea = trim(preg_replace('/\s+/', ' ', $linea));
            if ($linea === '') {
                $contenido .= "T*\n";
                continue;
            }

            foreach (str_split($linea, 92) as $fragmento) {
                $contenido .= '(' . $this->escapar($fragmento) . ") Tj\nT*\n";
            }
        }

        $contenido .= "ET\n";

        return $this->ensamblarPdf($contenido);
    }

    private function ensamblarPdf(string $stream): string
    {
        $objetos = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objetos as $objeto) {
            $offsets[] = strlen($pdf);
            $pdf .= $objeto;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objetos); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapar(string $texto): string
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }
}
