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
        $tiposConsumidos = SolicitudCertificacion::query()
            ->where('funcionario_id', $funcionarioId)
            ->whereDate('periodo_mes', $periodo->toDateString())
            ->pluck('tipo_certificado')
            ->map(fn ($valor) => $valor instanceof \BackedEnum ? $valor->value : (string) $valor)
            ->all();

        return [
            'periodo' => $periodo->toDateString(),
            'sencillo' => $this->estadoModalidad(in_array('sencillo', $tiposConsumidos, true), $periodo),
            'funciones' => $this->estadoModalidad(in_array('funciones', $tiposConsumidos, true), $periodo),
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
