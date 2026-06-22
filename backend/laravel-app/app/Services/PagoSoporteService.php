<?php

namespace App\Services;

use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PagoSoporteService
{
    /**
     * Almacena el archivo en disco privado y crea el registro PagoSoporte.
     * También avanza la solicitud a estado 'pago_pendiente'.
     */
    public function cargar(
        SolicitudCertificacion $solicitud,
        UploadedFile $archivo,
        User $funcionario,
        ?string $observaciones = null,
    ): PagoSoporte {
        // Guardar en disco privado: pagos-soportes/{solicitud_id}/{timestamp}_{nombre}
        $carpeta   = "pagos-soportes/{$solicitud->id}";
        $nombreSeg = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $archivo->getClientOriginalName());
        $ruta      = Storage::disk('local')->putFileAs($carpeta, $archivo, $nombreSeg);

        // Crear o actualizar el registro de soporte.
        // Una solicitud solo conserva un soporte activo a la vez.
        $pago = PagoSoporte::updateOrCreate(
            ['solicitud_certificacion_id' => $solicitud->id],
            [
                'funcionario_id'          => $solicitud->funcionario_id,
                'archivo_path'            => $ruta,
                'archivo_original_nombre' => $archivo->getClientOriginalName(),
                'estado'                  => EstadoPagoEnum::Cargado,
                'observaciones'           => $observaciones,
                'validado_por'            => null,
                'validado_at'             => null,
            ]
        );

        $solicitud->update(['estado' => EstadoSolicitudEnum::PagoEnRevision]);

        return $pago;
    }

    /**
     * Genera una URL temporal firmada para descargar el soporte de forma segura.
     * El archivo nunca se expone públicamente por URL directa.
     */
    public function rutaDescarga(PagoSoporte $pago): string
    {
        return Storage::disk('local')->path($pago->archivo_path);
    }

    /**
     * Elimina el archivo físico del disco al rechazar o cancelar.
     */
    public function eliminarArchivo(PagoSoporte $pago): void
    {
        if (Storage::disk('local')->exists($pago->archivo_path)) {
            Storage::disk('local')->delete($pago->archivo_path);
        }
    }
}
