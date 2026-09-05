<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateParametroPagoRequest;
use App\Models\ParametroSistema;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParametroCertificacionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RegistrarAuditoriaAction $registrarAuditoria) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('parametros.ver');

        return $this->successResponse($this->payload(), 'Configuración de certificaciones consultada.');
    }

    public function update(UpdateParametroPagoRequest $request): JsonResponse
    {
        $parametro = ParametroSistema::firstOrCreate(
            ['clave' => 'requiere_pago_certificado'],
            ['valor' => 'false', 'tipo' => 'boolean', 'descripcion' => 'Exige confirmación de pago antes de expedir certificados.'],
        );
        $anterior = filter_var($parametro->valor, FILTER_VALIDATE_BOOLEAN);
        $nuevo = $request->boolean('requiere_pago_certificado');

        $parametro->update(['valor' => $nuevo ? 'true' : 'false', 'updated_by' => $request->user()->id]);
        $this->registrarAuditoria->execute(
            accion: 'configurar_pago_certificado',
            modelo: 'ParametroSistema',
            modeloId: $parametro->id,
            descripcion: 'El administrador actualizó el requisito de pago de las certificaciones.',
            metadata: ['valor_anterior' => $anterior, 'valor_nuevo' => $nuevo],
        );

        return $this->successResponse($this->payload(), 'Configuración actualizada correctamente.');
    }

    private function payload(): array
    {
        $activo = (bool) ParametroSistema::valor('requiere_pago_certificado', false);

        return [
            'requiere_pago_certificado' => $activo,
            'descripcion' => $activo
                ? 'Las solicitudes requieren confirmación de pago antes de generar la certificación.'
                : 'Las certificaciones se generan automáticamente sin pago.',
        ];
    }
}
