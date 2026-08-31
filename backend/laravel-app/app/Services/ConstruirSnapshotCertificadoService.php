<?php

namespace App\Services;

use App\Enums\TipoCertificadoEnum;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Carbon\CarbonImmutable;

class ConstruirSnapshotCertificadoService
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly ResolverFuncionesCargoService $resolverFunciones,
        private readonly ResolverSalarioFuncionarioService $resolverSalario,
    ) {}

    public function construir(SolicitudCertificacion $solicitud, User $generadoPor): array
    {
        $solicitud->loadMissing('funcionario.cargo', 'funcionario.historialCargos.cargo');
        $funcionario = $solicitud->funcionario;
        $cargo = $funcionario?->cargo;
        $fecha = CarbonImmutable::now(config('app.timezone'));

        $snapshot = [
            'schema_version' => self::SCHEMA_VERSION,
            'funcionario' => [
                'nombres' => $funcionario?->nombres,
                'apellidos' => $funcionario?->apellidos,
                'tipo_documento' => $funcionario?->tipo_documento,
                'numero_documento' => $funcionario?->numero_documento,
                'fecha_ingreso' => $funcionario?->fecha_ingreso?->toDateString(),
                'fecha_retiro' => $funcionario?->fecha_retiro?->toDateString(),
            ],
            'cargo' => [
                'denominacion' => $cargo?->denominacion,
                'codigo' => $cargo?->codigo,
                'grado' => $cargo?->grado,
                'nivel' => $cargo?->nivel,
                'dependencia' => $funcionario?->dependencia ?? $cargo?->dependencia,
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
            $snapshot['manual_funciones'] = $this->resolverFunciones->resolver($cargo->id, $fecha);
        }

        return $snapshot;
    }
}
