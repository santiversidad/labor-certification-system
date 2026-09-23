<?php

namespace App\Services;

use App\Enums\TipoCertificadoEnum;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;

class ConstruirSnapshotCertificadoService
{
    public const SCHEMA_VERSION = 3;

    public function __construct(
        private readonly ResolverFuncionesFuncionarioService $resolverFunciones,
    ) {}

    public function construir(SolicitudCertificacion $solicitud, User $generadoPor): array
    {
        $solicitud->loadMissing('funcionario.cargo', 'funcionario.historialCargos.cargo');
        $funcionario = $solicitud->funcionario;
        $fecha = CarbonImmutable::now(config('app.timezone'));
        $asignacion = $this->resolverFunciones->asignacion($funcionario, $fecha);
        $cargo = $asignacion->cargo;
        $conFunciones = $solicitud->tipo_certificado === TipoCertificadoEnum::Funciones;
        $manual = $conFunciones ? $this->resolverFunciones->resolver($funcionario, $fecha, true) : null;

        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'asignacion_id' => $asignacion->id,
            'asignacion' => [
                'id' => $asignacion->id, 'cargo_id' => $asignacion->cargo_id,
                'tipo_vinculacion' => $asignacion->tipo_vinculacion->value,
                'naturaleza_cargo' => $asignacion->naturaleza_cargo->value,
                'fecha_inicio' => $asignacion->fecha_inicio->toDateString(),
                'fecha_fin' => $asignacion->fecha_fin?->toDateString(),
                'es_cargo_base' => $asignacion->es_cargo_base, 'es_encargo' => $asignacion->es_encargo,
            ],
            'funcionario' => [
                'id' => $funcionario->id,
                'nombres' => $funcionario?->nombres,
                'apellidos' => $funcionario?->apellidos,
                'tipo_documento' => $funcionario?->tipo_documento,
                'numero_documento' => $funcionario?->numero_documento,
                'fecha_ingreso' => $funcionario?->fecha_ingreso?->toDateString(),
                'fecha_retiro' => $funcionario?->fecha_retiro?->toDateString(),
            ],
            'cargo' => [
                'id' => $cargo->id,
                'denominacion' => $cargo?->denominacion,
                'codigo' => $cargo?->codigo,
                'grado' => $cargo?->grado,
                'nivel' => $cargo?->nivel,
                'dependencia' => $manual['dependencia'] ?? $funcionario->dependencia ?? $cargo?->dependencia,
            ],
            'tipo_certificado' => $solicitud->tipo_certificado->value,
            'fecha_expedicion' => $fecha->toIso8601String(),
            'generado_por' => ['id' => $generadoPor->id, 'nombre' => $generadoPor->name],
            'fuentes_institucionales' => [
                'asignacion' => 'funcionario_cargo',
                'acto_administrativo_id' => $asignacion->acto_administrativo_id,
            ],
        ];

        if ($conFunciones && $cargo) {
            $snapshot['manual'] = $manual;
            $snapshot['ficha'] = [
                'id' => $manual['ficha_id'],
                'source_id' => $manual['source_id'],
                'area_funcional' => $manual['area_funcional'],
                'proposito_principal' => $manual['proposito_principal'],
            ];
            $snapshot['funciones'] = array_values(array_map(
                fn (array $funcion): array => [
                    'orden' => $funcion['orden'],
                    'descripcion' => $funcion['descripcion'],
                    'tipo' => 'especifica',
                ],
                $manual['funciones_especificas']
            ));
            $snapshot['fuentes_institucionales']['manual'] = [
                'manual_id' => $manual['manual_id'],
                'version_id' => $manual['version_id'],
                'ficha_id' => $manual['ficha_id'],
                'source_id' => $manual['source_id'],
                'content_hash' => $manual['content_hash'],
                'relacion_normativa' => $manual['relacion_normativa'],
            ];
        }

        return $snapshot;
    }
}
