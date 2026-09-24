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

    public function construir(
        SolicitudCertificacion $solicitud,
        User $generadoPor,
        ?string $codigoTecnico = null,
        ?string $urlValidacionTecnica = null,
    ): array {
        $solicitud->loadMissing('funcionario.cargo', 'funcionario.historialCargos.cargo');
        $funcionario = $solicitud->funcionario;
        $fecha = CarbonImmutable::now(config('app.timezone'));
        $asignacion = $this->resolverFunciones->asignacion($funcionario, $fecha);
        $cargo = $asignacion->cargo;
        $esFunciones = $solicitud->tipo_certificado === TipoCertificadoEnum::Funciones;
        $manual = $esFunciones ? $this->resolverFunciones->resolver($funcionario, $fecha) : null;

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
                'dependencia' => $manual['dependencia'] ?? $funcionario?->dependencia ?? $cargo?->dependencia,
            ],
            'tipo_certificado' => $solicitud->tipo_certificado->value,
            'fecha_generacion' => $fecha->toIso8601String(),
            'expedicion' => [
                'fecha_expedicion' => $fecha->toDateString(),
                'radicado' => $solicitud->radicado,
                'codigo_tecnico' => $codigoTecnico,
                'url_validacion_tecnica' => $urlValidacionTecnica,
            ],
            'generado_por' => ['id' => $generadoPor->id, 'nombre' => $generadoPor->name],
        ];

        if ($esFunciones) {
            $snapshot['manual_funciones'] = $manual;
        }

        return $snapshot;
    }
}
