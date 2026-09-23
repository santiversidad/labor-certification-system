<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoCertificadoEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ValidacionCertificadoResource;
use App\Services\TokenValidacionService;
use App\Services\VerificarIntegridadCertificadoService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ValidacionPublicaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TokenValidacionService $tokenValidacionService,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
        private readonly VerificarIntegridadCertificadoService $verificarIntegridad,
    ) {}

    public function show(string $token): JsonResponse
    {
        $tokenModel = $this->tokenValidacionService->buscarTokenVigente($token);

        if (! $tokenModel || ! $tokenModel->estaVigente() || ! $tokenModel->certificado) {
            return $this->errorResponse(
                'Certificado no encontrado o código de validación inválido.',
                null,
                404
            )->setData([
                'success' => false,
                'message' => 'Certificado no encontrado o código de validación inválido.',
                'data' => ['valido' => false, 'resultado' => 'no_encontrado'],
            ]);
        }

        $certificado = $tokenModel->certificado;
        $estado = $certificado->estado instanceof EstadoCertificadoEnum
            ? $certificado->estado
            : EstadoCertificadoEnum::from($certificado->estado);

        $integridad = $this->verificarIntegridad->verificar($certificado);
        $integro = $integridad === VerificarIntegridadCertificadoService::OK;
        $vigente = $estado->esVigenteParaValidacion() && $integro;

        if (! $integro) {
            $resultado = 'integridad_comprometida';
            $mensaje = 'El documento no pudo validarse.';
        } elseif ($estado === EstadoCertificadoEnum::Anulado) {
            $resultado = 'anulado';
            $mensaje = 'CERTIFICADO ANULADO';
        } else {
            $resultado = $vigente ? 'valido' : 'no_vigente';
            $mensaje = $vigente ? 'Certificado válido.' : 'El certificado existe, pero no se encuentra vigente.';
        }

        $this->registrarAuditoria->execute(
            accion: 'validar_certificado_publico',
            modelo: 'Certificado',
            modeloId: $certificado->id,
            descripcion: "Validacion publica del certificado {$certificado->codigo_unico}.",
            metadata: ['valido' => $vigente, 'resultado' => $resultado],
        );

        $snapshot = $certificado->snapshot_datos ?? [];
        $data = [
            'valido' => $vigente,
            'resultado' => $resultado,
            'codigo_unico' => $certificado->codigo_unico,
            'fecha_generacion' => $certificado->fecha_generacion?->toDateString(),
            'estado' => $estado->value,
            'mensaje' => $mensaje,
            'funcionario' => isset($snapshot['funcionario']) ? [
                'nombre' => trim(($snapshot['funcionario']['nombres'] ?? '').' '.($snapshot['funcionario']['apellidos'] ?? '')),
            ] : null,
            'cargo' => $snapshot['cargo']['denominacion'] ?? null,
            'tipo_certificado' => $snapshot['tipo_certificado'] ?? null,
        ];

        return $this->successResponse(new ValidacionCertificadoResource($data), $mensaje);
    }
}
