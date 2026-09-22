<?php

namespace App\Services;

use App\Enums\TipoCertificadoEnum;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;

class ConstruirSnapshotCertificadoService
{
    public const SCHEMA_VERSION = 2;

    public function __construct(
        private readonly ResolverFuncionesFuncionarioService $resolverFunciones,
        private readonly ResolverSalarioFuncionarioService $resolverSalario,
    ) {}

    public function construir(SolicitudCertificacion $solicitud, User $generadoPor): array
    {
        $solicitud->loadMissing('funcionario.cargo', 'funcionario.historialCargos.cargo');
        $funcionario = $solicitud->funcionario;
        $fecha = CarbonImmutable::now(config('app.timezone'));
        $asignacion = $this->resolverFunciones->asignacion($funcionario, $fecha);
        $cargo = $asignacion->cargo;
        $manual = $this->resolverFunciones->resolver($funcionario, $fecha, $solicitud->tipo_certificado === TipoCertificadoEnum::Funciones);

        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'manual' => $manual,
            'funciones_especificas' => $manual['funciones_especificas'],
            'funciones_comunes' => $manual['funciones_comunes'],
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
                'dependencia' => $manual['dependencia'],
            ],
            'modalidad' => [
                'requiere_salario' => (bool) $solicitud->requiere_salario,
                'descripcion' => $solicitud->requiere_salario ? 'CON SALARIO' : 'SIN SALARIO',
            ],
            'tipo_certificado' => $solicitud->tipo_certificado->value,
            'fecha_generacion' => $fecha->toIso8601String(),
            'generado_por' => ['id' => $generadoPor->id, 'nombre' => $generadoPor->name],
        ];

        if ($solicitud->requiere_salario) {
            // Decisión temporal: la fecha de generación es la referencia salarial.
            $snapshot['salario'] = $this->resolverSalario->resolver($funcionario, $fecha);
        }

        if ($solicitud->tipo_certificado === TipoCertificadoEnum::Funciones && $cargo) {
            $snapshot['manual_funciones'] = $manual;
        }

        return $snapshot;
    }
}
