<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CertificateTemplateDefinition
{
    public const FONTS = ['DejaVu Sans', 'DejaVu Serif', 'DejaVu Sans Mono'];

    public const FOOTER = ['direccion', 'nit', 'telefono', 'codigo_postal', 'sitio_web', 'cuenta_x', 'ciudad'];

    public function defaults(string $type): array
    {
        $text = fn ($s) => ['type' => 'text', 'text' => $s];
        $variable = fn ($s) => ['type' => 'variable', 'variable' => $s];

        return ['schema_version' => 1, 'title' => 'CERTIFICACIÓN LABORAL',
            'header' => ['left_asset_id' => null, 'right_asset_id' => null],
            'typography' => ['font' => 'DejaVu Sans', 'size' => 10, 'line_height' => 1.4],
            'paragraphs' => [
                ['id' => 'certificacion', 'position' => 'body', 'alignment' => 'justify', 'runs' => [
                    $text('La Dirección de Personal de la Alcaldía de Villavicencio certifica la información laboral de '),
                    $variable('nombre_completo'), $text(', con documento '), $variable('tipo_documento'), $text(' '),
                    $variable('numero_documento'), $text(', registrada al momento de la expedición.'),
                ]],
                ['id' => 'cierre', 'position' => 'closing', 'alignment' => 'left', 'runs' => [
                    $text('Se expide el presente borrador el '), $variable('fecha_expedicion'), $text('.'),
                ]],
            ],
            'functions' => $type === 'funciones' ? ['title' => 'Funciones del cargo', 'size' => 10, 'line_height' => 1.4] : null,
            'signature' => ['name' => '', 'role' => 'Dirección de Personal', 'office' => '', 'text' => '', 'alignment' => 'center'],
            'additional_signatures' => [],
            // Transcribed only from the user-supplied reference; no personal salary or signatory data.
            'footer' => ['direccion' => 'Calle 40 No. 33 - 64, Barrio Centro, Piso 8', 'nit' => '892.099.324-3',
                'telefono' => '6715815 / 3156495910', 'codigo_postal' => '500001', 'sitio_web' => 'https://www.villavicencio.gov.co',
                'cuenta_x' => '@villavoalcaldia', 'ciudad' => 'Villavicencio, Meta'],
        ];
    }

    public function validar(array $content, string $type, bool $publishing = false): array
    {
        if ($type === 'sencillo' && (! array_key_exists('functions', $content) || $content['functions'] !== null)) {
            $this->invalid('contenido.functions', 'La plantilla sencilla no admite el bloque de funciones.');
        }
        $align = Rule::in(['left', 'center', 'right', 'justify']);
        $rules = [
            'schema_version' => ['required', 'integer', Rule::in([1])], 'title' => ['required', 'string', 'max:140'],
            'header' => ['required', 'array:left_asset_id,right_asset_id'],
            'header.left_asset_id' => ['present', 'nullable', 'integer', 'min:1'],
            'header.right_asset_id' => ['present', 'nullable', 'integer', 'min:1'],
            'typography' => ['required', 'array:font,size,line_height'],
            'typography.font' => ['required', Rule::in(self::FONTS)], 'typography.size' => ['required', 'integer', 'between:9,12'],
            'typography.line_height' => ['required', 'numeric', 'between:1.2,1.6'],
            'paragraphs' => ['required', 'array', 'list', 'min:2', 'max:12'],
            'paragraphs.*' => ['required', 'array:id,position,alignment,runs'],
            'paragraphs.*.id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/', 'distinct'],
            'paragraphs.*.position' => ['required', Rule::in(['body', 'closing'])],
            'paragraphs.*.alignment' => ['required', $align],
            'paragraphs.*.runs' => ['required', 'array', 'list', 'min:1', 'max:40'],
            'paragraphs.*.runs.*' => ['required', 'array:type,text,variable,bold,italic'],
            'paragraphs.*.runs.*.type' => ['required', Rule::in(['text', 'variable'])],
            'paragraphs.*.runs.*.text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'paragraphs.*.runs.*.variable' => ['sometimes', 'required', Rule::in(array_column(app(CertificateTemplateVariables::class)->catalogo($type), 'id'))],
            'paragraphs.*.runs.*.bold' => ['sometimes', 'boolean'], 'paragraphs.*.runs.*.italic' => ['sometimes', 'boolean'],
            'functions' => $type === 'funciones' ? ['required', 'array:title,size,line_height'] : ['present', 'nullable', 'prohibited'],
            'signature' => ['required', 'array:name,role,office,text,alignment'],
            'signature.name' => ['present', 'nullable', 'string', 'max:80'], 'signature.role' => ['present', 'nullable', 'string', 'max:100'],
            'signature.office' => ['present', 'nullable', 'string', 'max:100'], 'signature.text' => ['present', 'nullable', 'string', 'max:200'],
            'signature.alignment' => ['required', $align],
            'additional_signatures' => ['present', 'array', 'list', 'max:3'],
            'additional_signatures.*' => ['required', 'array:label,name,role'],
            'additional_signatures.*.label' => ['required', 'string', 'max:30'],
            'additional_signatures.*.name' => ['present', 'nullable', 'string', 'max:80'],
            'additional_signatures.*.role' => ['present', 'nullable', 'string', 'max:100'],
            'footer' => ['required', 'array:'.implode(',', self::FOOTER)],
        ];
        if ($type === 'funciones') {
            $rules += ['functions.title' => ['required', 'string', 'max:100'], 'functions.size' => ['required', 'integer', 'between:9,12'],
                'functions.line_height' => ['required', 'numeric', 'between:1.2,1.6']];
        }
        foreach (self::FOOTER as $field) {
            $rules['footer.'.$field] = ['present', 'nullable', 'string', 'max:'.match ($field) {
                'direccion' => 120, 'sitio_web' => 90, 'telefono' => 45, 'nit' => 30, 'codigo_postal' => 15, default => 60,
            }];
        }
        $unknown = array_diff(array_keys($content), ['schema_version', 'title', 'header', 'typography', 'paragraphs', 'functions', 'signature', 'additional_signatures', 'footer']);
        if ($unknown) {
            $this->invalid('contenido', 'La plantilla contiene atributos no autorizados.');
        }
        Validator::make($content, $rules)->validate();
        if (strlen(json_encode($content)) > 40000) {
            $this->invalid('contenido', 'El contenido supera el tamaño máximo permitido.');
        }
        $this->safeText($content);
        $paragraphs = collect($content['paragraphs'])->keyBy('id');
        if (($paragraphs->get('certificacion')['position'] ?? null) !== 'body' || ($paragraphs->get('cierre')['position'] ?? null) !== 'closing') {
            $this->invalid('contenido.paragraphs', 'Conserve los párrafos obligatorios de certificación y cierre en su sección.');
        }
        foreach ($content['paragraphs'] as &$paragraph) {
            $visible = false;
            foreach ($paragraph['runs'] as &$run) {
                $isText = $run['type'] === 'text';
                if (($isText && (! array_key_exists('text', $run) || array_key_exists('variable', $run)))
                    || (! $isText && (! isset($run['variable']) || array_key_exists('text', $run)))) {
                    $this->invalid('contenido.paragraphs', 'El segmento debe contener texto o una variable autorizada, sin atributos de otro tipo.');
                }
                if ($isText) {
                    $run['text'] = (string) ($run['text'] ?? '');
                }
                foreach (['bold', 'italic'] as $style) {
                    if (isset($run[$style]) && ! is_bool($run[$style])) {
                        $this->invalid('contenido.paragraphs', 'Los estilos deben ser valores booleanos.');
                    }
                }
                $visible = $visible || ! $isText || trim($run['text']) !== '';
            }
            unset($run);
            if (! $visible) {
                $this->invalid('contenido.paragraphs', 'Complete el párrafo o elimínelo si es opcional.');
            }
        }
        unset($paragraph);
        foreach (['signature', 'footer'] as $section) {
            foreach ($content[$section] as &$value) {
                $value ??= '';
            }
            unset($value);
        }
        foreach ($content['additional_signatures'] as &$signature) {
            $signature['name'] ??= '';
            $signature['role'] ??= '';
        }
        unset($signature);
        if ($publishing && (! $content['header']['left_asset_id'] || ! $content['header']['right_asset_id'])) {
            $this->invalid('contenido.header', 'Configure las dos imágenes institucionales antes de publicar.');
        }

        return $content;
    }

    public static function hash(array $value): string
    {
        $sort = function (array $items) use (&$sort): array {
            if (! array_is_list($items)) {
                ksort($items);
            }
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $items;
        };

        return hash('sha256', json_encode($sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function safeText(array $values): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->safeText($value);
            } elseif (is_string($value) && (preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/u', $value) || str_contains($value, '{{') || str_contains($value, '}}'))) {
                $this->invalid('contenido', 'Use texto y el selector de variables; no se admite HTML ni expresiones ejecutables.');
            }
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
