<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\Funcionario;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    use ApiResponse;

    public function resumen(Request $request): JsonResponse
    {
        if (! $request->user()->can('reportes.ver')) {
            return $this->forbiddenResponse('No tiene permisos para consultar reportes.');
        }

        $promedio = SolicitudCertificacion::query()
            ->whereNotNull('reviewed_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (reviewed_at - created_at)) / 86400) as promedio')
            ->value('promedio');

        return $this->successResponse([
            'total_funcionarios'        => Funcionario::count(),
            'total_solicitudes'         => SolicitudCertificacion::count(),
            'solicitudes_pendientes'    => SolicitudCertificacion::whereIn('estado', [
                EstadoSolicitudEnum::Pendiente->value,
                EstadoSolicitudEnum::EnRevision->value,
                EstadoSolicitudEnum::PendientePago->value,
                EstadoSolicitudEnum::PagoEnRevision->value,
            ])->count(),
            'solicitudes_aprobadas'     => SolicitudCertificacion::whereIn('estado', [
                EstadoSolicitudEnum::Aprobada->value,
                EstadoSolicitudEnum::CertificadoGenerado->value,
            ])->count(),
            'solicitudes_rechazadas'    => SolicitudCertificacion::where('estado', EstadoSolicitudEnum::Rechazada->value)->count(),
            'certificados_generados'    => Certificado::count(),
            'pagos_pendientes'          => PagoSoporte::whereIn('estado', [
                EstadoPagoEnum::Pendiente->value,
                EstadoPagoEnum::Cargado->value,
            ])->count(),
            'tiempo_promedio_respuesta' => $promedio !== null ? round((float) $promedio, 2) : null,
        ], 'Resumen consultado correctamente.');
    }
}
