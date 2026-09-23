<?php

namespace App\Services;

class PdfBasicoService
{
    private const PAGE_WIDTH = 612;
    private const PAGE_HEIGHT = 792;
    private const LEFT = 62;
    private const CONTENT_TOP = 650;
    private const CONTENT_BOTTOM = 105;

    /** Genera el borrador exclusivamente desde el snapshot estructurado e inmutable. */
    public function generarCertificado(array $snapshot, string $codigo, string $urlValidacion): string
    {
        $tipo = $snapshot['tipo_certificado'] ?? null;
        if (! in_array($tipo, ['sencillo', 'funciones'], true)) {
            throw new \DomainException('TIPO_CERTIFICADO_INVALIDO');
        }

        $funcionario = $snapshot['funcionario'] ?? [];
        $cargo = $snapshot['cargo'] ?? [];
        $asignacion = $snapshot['asignacion'] ?? [];
        $nombre = trim(($funcionario['nombres'] ?? '').' '.($funcionario['apellidos'] ?? ''));
        $documento = trim(($funcionario['tipo_documento'] ?? '').' '.($funcionario['numero_documento'] ?? ''));
        $fecha = substr((string) ($snapshot['fecha_expedicion'] ?? ''), 0, 10);

        $bloques = [$this->bloque('CERTIFICACIÓN LABORAL', 'F2', 16, 20, 'center', [26, 73, 118], 0, 6)];
        if ($tipo === 'funciones') {
            $bloques[] = $this->bloque('CON FUNCIONES', 'F2', 10, 14, 'center', [49, 105, 76], 0, 10);
        }
        $bloques[] = $this->bloque('BORRADOR - SIN VALIDEZ OFICIAL', 'F2', 10, 14, 'center', [151, 39, 44], 6, 16, true);
        $bloques[] = $this->bloque('[Autoridad/Dependencia competente] CERTIFICA:', 'F2', 11, 16, 'left', [26, 73, 118], 0, 12);
        $bloques[] = $this->parrafo('Que, de acuerdo con la información institucional disponible, se registran los siguientes datos de vinculación laboral:', 10, 15, 0, 12);

        foreach ([
            ['Nombre completo', $nombre],
            ['Identificación', $documento],
            ['Situación o vinculación', $this->etiqueta((string) ($asignacion['tipo_vinculacion'] ?? ''))],
            ['Naturaleza del empleo', $this->etiqueta((string) ($asignacion['naturaleza_cargo'] ?? ''))],
            ['Denominación del empleo', (string) ($cargo['denominacion'] ?? '')],
            ['Código', (string) ($cargo['codigo'] ?? '')],
            ['Grado', (string) ($cargo['grado'] ?? '')],
            ['Dependencia', (string) ($cargo['dependencia'] ?? '')],
            ['Área funcional', $tipo === 'funciones' ? (string) ($snapshot['ficha']['area_funcional'] ?? '') : ''],
            ['Fecha de inicio de la asignación', (string) ($asignacion['fecha_inicio'] ?? '')],
            ['Fecha de finalización', (string) ($asignacion['fecha_fin'] ?? '')],
        ] as [$label, $value]) {
            if (trim($value) !== '') {
                $bloques[] = $this->campo($label, $value);
            }
        }

        if ($tipo === 'funciones') {
            $bloques[] = $this->bloque('FUNCIONES', 'F2', 12, 17, 'left', [26, 73, 118], 12, 8);
            $bloques[] = $this->parrafo('De conformidad con el Manual Específico de Funciones y Competencias Laborales aplicable al empleo, se certifican las siguientes funciones:', 10, 15, 0, 10);
            foreach ($snapshot['funciones'] ?? [] as $index => $funcion) {
                $numero = $funcion['orden'] ?? ($index + 1);
                $bloques[] = $this->parrafo($numero.'. '.($funcion['descripcion'] ?? ''), 9.5, 14, 8, 8, true);
            }
        }

        $bloques[] = $this->parrafo('La presente certificación se expide a solicitud del interesado en Villavicencio, en la fecha indicada en el documento.', 10, 15, 14, 14);
        $bloques[] = ['kind' => 'officialization', 'height' => 112, 'codigo' => $codigo, 'fecha' => $fecha, 'url' => $urlValidacion];

        return $this->ensamblarPdf($this->paginar($bloques), $codigo, $tipo);
    }

    private function campo(string $label, string $value): array
    {
        return $this->bloque($label.': '.$value, 'F1', 9.8, 14, 'left', [32, 43, 54], 0, 3, false, true);
    }

    private function parrafo(string $text, float $size, float $leading, int $before, int $after, bool $keepTogether = false): array
    {
        return $this->bloque($text, 'F1', $size, $leading, 'left', [32, 43, 54], $before, $after, false, $keepTogether);
    }

    private function bloque(string $text, string $font, float $size, float $leading, string $align, array $color, int $before, int $after, bool $banner = false, bool $keepTogether = false): array
    {
        $lines = $this->envolver($text, max(28, (int) floor(92 * (10 / $size))));

        return compact('lines', 'font', 'size', 'leading', 'align', 'color', 'before', 'after', 'banner', 'keepTogether')
            + ['kind' => 'text', 'height' => $before + $after + count($lines) * $leading];
    }

    private function paginar(array $bloques): array
    {
        $paginas = [[]];
        $remaining = self::CONTENT_TOP - self::CONTENT_BOTTOM;
        foreach ($bloques as $bloque) {
            if ($bloque['height'] <= $remaining) {
                $paginas[array_key_last($paginas)][] = $bloque;
                $remaining -= $bloque['height'];
                continue;
            }
            if ($bloque['kind'] === 'text' && ! $bloque['keepTogether']) {
                $lines = $bloque['lines'];
                while ($lines !== []) {
                    $available = (int) floor(($remaining - $bloque['before'] - $bloque['after']) / $bloque['leading']);
                    if ($available < 2) {
                        $paginas[] = [];
                        $remaining = self::CONTENT_TOP - self::CONTENT_BOTTOM;
                        continue;
                    }
                    $piece = $bloque;
                    $piece['lines'] = array_splice($lines, 0, $available);
                    $piece['height'] = $piece['before'] + $piece['after'] + count($piece['lines']) * $piece['leading'];
                    $paginas[array_key_last($paginas)][] = $piece;
                    $remaining -= $piece['height'];
                    if ($lines !== []) {
                        $paginas[] = [];
                        $remaining = self::CONTENT_TOP - self::CONTENT_BOTTOM;
                    }
                }
                continue;
            }
            $paginas[] = [$bloque];
            $remaining = self::CONTENT_TOP - self::CONTENT_BOTTOM - $bloque['height'];
        }

        return array_values(array_filter($paginas, fn (array $pagina) => $pagina !== []));
    }

    private function ensamblarPdf(array $paginas, string $codigo, string $tipo): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        $total = count($paginas);
        foreach ($paginas as $index => $bloques) {
            $pageId = 5 + $index * 2;
            $streamId = $pageId + 1;
            $kids[] = "{$pageId} 0 R";
            $stream = $this->cabecera($tipo);
            $y = self::CONTENT_TOP;
            foreach ($bloques as $bloque) {
                if ($bloque['kind'] === 'officialization') {
                    $stream .= $this->oficializacion($bloque, $y);
                } else {
                    [$commands] = $this->texto($bloque, $y);
                    $stream .= $commands;
                }
                $y -= $bloque['height'];
            }
            $stream .= $this->pie($codigo, $index + 1, $total);
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$streamId} 0 R >>";
            $objects[$streamId] = '<< /Length '.strlen($stream).">>\nstream\n{$stream}endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$total.' >>';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function cabecera(string $tipo): string
    {
        $subtitulo = $tipo === 'funciones' ? 'BORRADOR INSTITUCIONAL - CON FUNCIONES' : 'BORRADOR INSTITUCIONAL - SENCILLO';

        return "q\n0.102 0.286 0.463 rg\n0 752 612 40 re f\nQ\n"
            .$this->linea('ALCALDÍA DE VILLAVICENCIO', 306, 733, 'F2', 13, 'center', [26, 73, 118])
            .$this->linea('SECRETARÍA DE DESARROLLO INSTITUCIONAL', 306, 714, 'F2', 9, 'center', [49, 63, 78])
            .$this->linea('DIRECCIÓN DE PERSONAL', 306, 698, 'F1', 9, 'center', [49, 63, 78])
            .$this->linea($subtitulo, 306, 678, 'F2', 8, 'center', [151, 39, 44])
            ."0.82 0.85 0.88 RG\n62 668 m 550 668 l S\n";
    }

    private function pie(string $codigo, int $page, int $total): string
    {
        return "0.82 0.85 0.88 RG\n62 83 m 550 83 l S\n"
            .$this->linea('BORRADOR - SIN VALIDEZ OFICIAL', 62, 65, 'F2', 8, 'left', [151, 39, 44])
            .$this->linea('Código de verificación: '.$codigo, 62, 49, 'F1', 7.5, 'left', [72, 82, 93])
            .$this->linea("Página {$page} de {$total}", 550, 49, 'F1', 7.5, 'right', [72, 82, 93]);
    }

    private function texto(array $bloque, float $top): array
    {
        $y = $top - $bloque['before'];
        $commands = '';
        if ($bloque['banner']) {
            $height = count($bloque['lines']) * $bloque['leading'] + 8;
            $commands .= "q\n0.98 0.93 0.93 rg\n62 ".($y - $height + 5)." 488 {$height} re f\nQ\n";
            $y -= 2;
        }
        foreach ($bloque['lines'] as $line) {
            $commands .= $this->linea($line, $bloque['align'] === 'center' ? 306 : self::LEFT + 8, $y, $bloque['font'], $bloque['size'], $bloque['align'], $bloque['color']);
            $y -= $bloque['leading'];
        }

        return [$commands, $bloque['height']];
    }

    private function oficializacion(array $bloque, float $top): string
    {
        $bottom = $top - $bloque['height'] + 4;

        return "q\n0.92 0.93 0.94 RG\n62 {$bottom} 488 102 re S\n438 ".($bottom + 12)." 92 76 re S\nQ\n"
            .$this->linea('BORRADOR - MECANISMO DE OFICIALIZACIÓN PENDIENTE', 76, $top - 20, 'F2', 8.5, 'left', [151, 39, 44])
            .$this->linea('Identidad del responsable: pendiente de definición institucional', 76, $top - 39, 'F1', 8, 'left', [72, 82, 93])
            .$this->linea('Firma o sello: no implementado en este borrador', 76, $top - 55, 'F1', 8, 'left', [72, 82, 93])
            .$this->linea('Código: '.$bloque['codigo'].'  |  Fecha: '.$bloque['fecha'], 76, $top - 74, 'F1', 7.5, 'left', [72, 82, 93])
            .$this->linea('QR', 484, $top - 50, 'F2', 13, 'center', [120, 128, 137])
            .$this->linea('pendiente', 484, $top - 66, 'F1', 7, 'center', [120, 128, 137]);
    }

    private function linea(string $text, float $x, float $y, string $font, float $size, string $align, array $color): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            throw new \DomainException('PDF_TEXTO_NO_REPRESENTABLE');
        }
        $width = strlen($encoded) * $size * ($font === 'F2' ? 0.56 : 0.50);
        $drawX = match ($align) { 'center' => $x - $width / 2, 'right' => $x - $width, default => $x };
        [$r, $g, $b] = array_map(fn ($value) => round($value / 255, 3), $color);

        return "BT\n{$r} {$g} {$b} rg\n/{$font} {$size} Tf\n1 0 0 1 ".round($drawX, 2).' '.round($y, 2)." Tm\n(".$this->escapar($encoded).") Tj\nET\n";
    }

    private function envolver(string $text, int $maxChars): array
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $text));
        return $normalized === '' ? [] : explode("\n", wordwrap($normalized, $maxChars, "\n", true));
    }

    private function etiqueta(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    private function escapar(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
