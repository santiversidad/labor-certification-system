<?php

namespace App\Services;

use App\Exceptions\CertificateTemplateException;
use Carbon\CarbonImmutable;

class CertificateTemplateVariables
{
    private const FIELDS = [
        'nombre_completo' => ['Nombre completo', 'Identificación', 'funcionario.nombres + funcionario.apellidos', 'texto', true],
        'tipo_documento' => ['Tipo de documento', 'Identificación', 'funcionario.tipo_documento', 'texto', true],
        'numero_documento' => ['Número de documento', 'Identificación', 'funcionario.numero_documento', 'texto', true],
        'fecha_vinculacion' => ['Fecha de vinculación', 'Vinculación', 'asignacion.fecha_inicio', 'fecha', true],
        'fecha_ingreso' => ['Fecha de ingreso', 'Vinculación', 'funcionario.fecha_ingreso', 'fecha', false],
        'fecha_retiro' => ['Fecha de retiro', 'Vinculación', 'funcionario.fecha_retiro', 'fecha', false],
        'dependencia' => ['Dependencia registrada', 'Vinculación', 'cargo.dependencia', 'texto', false],
        'denominacion_cargo' => ['Denominación del cargo', 'Vinculación', 'cargo.denominacion', 'texto', true],
        'codigo_cargo' => ['Código del cargo', 'Vinculación', 'cargo.codigo', 'texto', false],
        'grado_cargo' => ['Grado del cargo', 'Vinculación', 'cargo.grado', 'texto', false],
        'tipo_vinculacion' => ['Tipo de vinculación', 'Vinculación', 'asignacion.tipo_vinculacion', 'etiqueta', false],
        'naturaleza_cargo' => ['Naturaleza del cargo', 'Vinculación', 'asignacion.naturaleza_cargo', 'etiqueta', false],
        'fecha_expedicion' => ['Fecha de expedición', 'Documento', 'expedicion.fecha_expedicion', 'fecha', true],
        'numero_certificado' => ['Número técnico del certificado', 'Documento', 'expedicion.codigo_tecnico', 'texto', false],
        'radicado' => ['Radicado de solicitud', 'Documento', 'expedicion.radicado', 'texto', false],
        'pagina_manual' => ['Página verificada del Manual', 'Manual', 'manual_funciones.pagina_inicio', 'entero', false],
    ];

    public function catalogo(string $type): array
    {
        $rows = [];
        foreach (self::FIELDS as $key => [$label, $group, $source, $format, $required]) {
            if ($key === 'pagina_manual' && $type !== 'funciones') {
                continue;
            }
            $rows[] = ['id' => $key, 'etiqueta' => $label, 'grupo' => $group, 'descripcion' => $label.' capturado al expedir el documento.',
                'tipo' => $format === 'entero' ? 'numero' : ($format === 'fecha' ? 'fecha' : 'texto'), 'origen' => 'snapshot.'.$source,
                'formato' => $format === 'fecha' ? 'D de mes de YYYY' : $format,
                'disponibilidad' => $key === 'pagina_manual' ? 'funciones' : 'ambos',
                'ausencia' => $required ? 'bloquear_render' : 'omitir_parrafo'];
        }

        return $rows;
    }

    public function resolver(string $key, array $snapshot): ?string
    {
        // Explicit resolvers: user input never becomes an object path or executable expression.
        $value = match ($key) {
            'nombre_completo' => trim(($snapshot['funcionario']['nombres'] ?? '').' '.($snapshot['funcionario']['apellidos'] ?? '')),
            'tipo_documento' => $snapshot['funcionario']['tipo_documento'] ?? null,
            'numero_documento' => $snapshot['funcionario']['numero_documento'] ?? null,
            'fecha_vinculacion' => $snapshot['asignacion']['fecha_inicio'] ?? null,
            'fecha_ingreso' => $snapshot['funcionario']['fecha_ingreso'] ?? null,
            'fecha_retiro' => $snapshot['funcionario']['fecha_retiro'] ?? null,
            'dependencia' => $snapshot['cargo']['dependencia'] ?? null,
            'denominacion_cargo' => $snapshot['cargo']['denominacion'] ?? null,
            'codigo_cargo' => $snapshot['cargo']['codigo'] ?? null,
            'grado_cargo' => $snapshot['cargo']['grado'] ?? null,
            'tipo_vinculacion' => $snapshot['asignacion']['tipo_vinculacion'] ?? null,
            'naturaleza_cargo' => $snapshot['asignacion']['naturaleza_cargo'] ?? null,
            'fecha_expedicion' => $snapshot['expedicion']['fecha_expedicion'] ?? null,
            'numero_certificado' => $snapshot['expedicion']['codigo_tecnico'] ?? null,
            'radicado' => $snapshot['expedicion']['radicado'] ?? null,
            'pagina_manual' => $snapshot['tipo_certificado'] === 'funciones' ? ($snapshot['manual_funciones']['pagina_inicio'] ?? null) : null,
            default => throw new \DomainException('TEMPLATE_VARIABLE_UNKNOWN'),
        };
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '' || preg_match('/^(null|undefined|n\/?a)$/iu', $value)) {
            if (self::FIELDS[$key][4]) {
                throw new CertificateTemplateException('TEMPLATE_REQUIRED_DATA_MISSING', 'Falta información obligatoria para la variable '.self::FIELDS[$key][0].'.');
            }

            return null;
        }
        if (self::FIELDS[$key][3] === 'fecha') {
            try {
                return CarbonImmutable::parse($value)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
            } catch (\Throwable) {
                throw new CertificateTemplateException('TEMPLATE_DATE_INVALID', 'La fecha capturada no se puede presentar.');
            }
        }

        return self::FIELDS[$key][3] === 'etiqueta' ? ucfirst(str_replace('_', ' ', $value)) : $value;
    }
}
