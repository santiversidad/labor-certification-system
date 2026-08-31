<?php

namespace App\Services;

use App\Models\SolicitudCertificacion;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DisponibilidadCertificacionService
{
    public function periodo(?CarbonInterface $fecha = null): CarbonImmutable
    {
        $fechaLocal = $fecha
            ? CarbonImmutable::instance($fecha)->setTimezone(config('app.timezone'))
            : CarbonImmutable::now(config('app.timezone'));

        return $fechaLocal->startOfMonth();
    }

    public function consultar(int $funcionarioId, ?CarbonInterface $fecha = null): array
    {
        $periodo = $this->periodo($fecha);
        $modalidadesConsumidas = SolicitudCertificacion::query()
            ->where('funcionario_id', $funcionarioId)
            ->whereDate('periodo_mes', $periodo->toDateString())
            ->pluck('requiere_salario')
            ->map(fn ($valor) => (bool) $valor)
            ->all();

        return [
            'periodo' => $periodo->toDateString(),
            'con_salario' => $this->estadoModalidad(in_array(true, $modalidadesConsumidas, true), $periodo),
            'sin_salario' => $this->estadoModalidad(in_array(false, $modalidadesConsumidas, true), $periodo),
        ];
    }

    private function estadoModalidad(bool $consumida, CarbonImmutable $periodo): array
    {
        return [
            'puede_solicitar' => ! $consumida,
            'proxima_fecha_disponible' => $consumida ? $periodo->addMonth()->toDateString() : null,
        ];
    }
}
