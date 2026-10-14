<?php

namespace App\Services;

class CertificateTemplatePreview
{
    public function snapshot(string $type, string $scenario): array
    {
        $snapshot = ['schema_version' => 3, 'tipo_certificado' => $type, 'vista_previa_sintetica' => true,
            'fecha_generacion' => '2026-10-12T10:00:00-05:00',
            'funcionario' => ['nombres' => 'Persona', 'apellidos' => 'de Ejemplo', 'tipo_documento' => 'CC', 'numero_documento' => 'DOCUMENTO-SINTETICO', 'fecha_ingreso' => '2020-01-15', 'fecha_retiro' => null],
            'cargo' => ['denominacion' => 'Profesional de ejemplo', 'codigo' => 'EJE', 'grado' => '01', 'dependencia' => 'Dependencia de ejemplo'],
            'asignacion' => ['tipo_vinculacion' => 'planta', 'naturaleza_cargo' => 'carrera_administrativa', 'fecha_inicio' => '2020-01-15', 'fecha_fin' => null],
            'expedicion' => ['fecha_expedicion' => '2026-10-12', 'radicado' => 'VISTA-PREVIA', 'codigo_tecnico' => 'EJEMPLO-NO-EMITIDO'],
        ];
        if ($type === 'funciones') {
            $count = match ($scenario) {
                'dos' => 12, 'tres' => 20, 'extenso' => 48, default => 2
            };
            $functions = [];
            for ($number = 1; $number <= $count; $number++) {
                $functions[] = ['orden' => $number, 'numero_fuente' => $number, 'grupo' => '',
                    'descripcion' => "Función sintética {$number}: verificar información de ejemplo y conservar su orden documental.".($scenario === 'breve' ? '' : str_repeat(' Este contenido ficticio permite revisar saltos de página, márgenes y legibilidad sin consultar datos personales ni fichas reales.', 2))];
            }
            $snapshot['manual_funciones'] = ['manual_nombre' => 'Manual sintético de vista previa', 'version' => 'EJEMPLO',
                'source_id' => 'FICHA-SINTETICA', 'pagina_inicio' => 100, 'area_funcional' => 'Área de ejemplo',
                'proposito_principal' => 'Revisar la composición documental.', 'funciones' => $functions];
        }

        return $snapshot;
    }
}
