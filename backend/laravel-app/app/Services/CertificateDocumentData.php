<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final class CertificateDocumentData
{
    /**
     * Convert the immutable snapshot into presentation-only data.
     *
     * This class deliberately has no model or database dependencies. Keeping
     * this boundary explicit prevents an issued certificate from being rebuilt
     * with mutable relationships.
     */
    public function fromSnapshot(array $snapshot): array
    {
        if (($snapshot['schema_version'] ?? null) !== ConstruirSnapshotCertificadoService::SCHEMA_VERSION) {
            throw new \DomainException('CERTIFICATE_SNAPSHOT_VERSION_UNSUPPORTED');
        }

        $type = $snapshot['tipo_certificado'] ?? null;
        if (! in_array($type, ['sencillo', 'funciones'], true)) {
            throw new \DomainException('CERTIFICATE_TYPE_UNSUPPORTED');
        }

        $employee = $snapshot['funcionario'] ?? [];
        $position = $snapshot['cargo'] ?? [];
        $assignment = $snapshot['asignacion'] ?? [];
        $issuance = $snapshot['expedicion'] ?? [];
        $manual = $type === 'funciones' ? ($snapshot['manual_funciones'] ?? null) : null;

        if ($type === 'funciones' && ! is_array($manual)) {
            throw new \DomainException('CERTIFICATE_FUNCTIONS_SNAPSHOT_INCOMPLETE');
        }

        $fullName = trim(implode(' ', array_filter([
            $this->text($employee['nombres'] ?? null),
            $this->text($employee['apellidos'] ?? null),
        ])));
        $document = trim(implode(' ', array_filter([
            $this->text($employee['tipo_documento'] ?? null),
            $this->text($employee['numero_documento'] ?? null),
        ])));
        $functions = [];
        foreach ($manual['funciones'] ?? [] as $function) {
            if (is_array($function) && $this->text($function['descripcion'] ?? null) !== null) {
                // The snapshot array is already in its source order. Never sort by description.
                $functions[] = ['number' => count($functions) + 1, 'description' => (string) $function['descripcion']];
            }
        }
        $functionPages = $this->functionPages($functions);

        $subject = $fullName;
        if ($document !== '') {
            $subject .= ($subject !== '' ? ', ' : '').'documento '.$document;
        }
        $body = 'La Dirección de Personal de la Alcaldía de Villavicencio certifica la información laboral'
            .($subject !== '' ? ' de '.$subject : '')
            .', registrada al momento de la expedición.';
        $issuanceDate = $this->date($issuance['fecha_expedicion'] ?? $snapshot['fecha_generacion'] ?? null);
        $filingCode = $this->text($issuance['radicado'] ?? null);
        $closing = 'Se expide el presente borrador'
            .($issuanceDate ? ' el '.$issuanceDate : '')
            .($filingCode ? ', bajo el radicado '.$filingCode : '').'.';

        return [
            'type' => $type,
            'type_label' => $type === 'funciones' ? 'CON FUNCIONES' : 'SENCILLO',
            'employee_name' => $fullName,
            'employee_document' => $document,
            'body' => $body,
            'employment' => array_values(array_filter([
                $this->field('Denominación del empleo', $position['denominacion'] ?? null),
                $this->field('Código', $position['codigo'] ?? null),
                $this->field('Grado', $position['grado'] ?? null),
                $this->field('Dependencia', $position['dependencia'] ?? null),
                $this->field('Tipo de vinculación', $this->label($assignment['tipo_vinculacion'] ?? null)),
                $this->field('Naturaleza del empleo', $this->label($assignment['naturaleza_cargo'] ?? null)),
                $this->field('Fecha de ingreso', $this->date($employee['fecha_ingreso'] ?? null)),
                $this->field('Inicio de la asignación', $this->date($assignment['fecha_inicio'] ?? null)),
                $this->field('Fin de la asignación', $this->date($assignment['fecha_fin'] ?? null)),
            ])),
            'manual' => $manual ? [
                'reference' => trim(implode(' · ', array_filter([
                    $this->text($manual['manual_nombre'] ?? null),
                    $this->prefixed('Versión', $manual['version'] ?? null),
                ]))),
                'profile' => $this->text($manual['source_id'] ?? $manual['ficha_id'] ?? null),
                'functional_area' => $this->text($manual['area_funcional'] ?? null),
                'purpose' => $this->text($manual['proposito_principal'] ?? $manual['proposito'] ?? null),
                'functions' => $functions,
                'function_pages' => $functionPages,
                'controlled_pages' => count($functionPages) > 1,
                'development_notice' => (bool) ($manual['prueba_desarrollo'] ?? false),
            ] : null,
            'issuance_date' => $issuanceDate,
            'filing_code' => $filingCode,
            'closing' => $closing,
            'technical_code' => $this->text($issuance['codigo_tecnico'] ?? null),
            'verification_url' => $this->text($issuance['url_validacion_tecnica'] ?? null),
        ];
    }

    private function field(string $label, mixed $value): ?array
    {
        $value = $this->text($value);

        return $value === null ? null : ['label' => $label, 'value' => $value];
    }

    private function functionPages(array $functions): array
    {
        $pages = [];
        $page = [];
        $characters = 0;
        foreach ($functions as $function) {
            $length = mb_strlen($function['description']);
            if ($page !== [] && (count($page) >= 6 || $characters + $length > 1500)) {
                $pages[] = $page;
                $page = [];
                $characters = 0;
            }
            $page[] = $function;
            $characters += $length;
        }
        if ($page !== []) {
            $pages[] = $page;
        }

        return $pages;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || preg_match('/^(null|undefined|n\/?a)$/iu', $value) ? null : $value;
    }

    private function prefixed(string $prefix, mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : "{$prefix} {$value}";
    }

    private function label(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        return ucfirst(str_replace('_', ' ', $value));
    }

    private function date(mixed $value): ?string
    {
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}(?:T.*)?$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }

        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $date->day.' de '.$months[$date->month].' de '.$date->year;
    }
}
