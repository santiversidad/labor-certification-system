<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoCertificadoEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\ValidacionCertificadoResource;
use App\Services\TokenValidacionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ValidacionPublicaController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TokenValidacionService $tokenValidacionService,
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    public function show(string $token): JsonResponse
    {
        $tokenModel = $this->tokenValidacionService->buscarTokenVigente($token);

        if (! $tokenModel || ! $tokenModel->estaVigente() || ! $tokenModel->certificado) {
            return $this->errorResponse(
                'No se encontro un certificado valido para el token suministrado.',
                null,
                404
            )->setData([
                'success' => false,
                'message' => 'No se encontro un certificado valido para el token suministrado.',
                'data' => ['valido' => false],
            ]);
        }

        $certificado = $tokenModel->certificado;
        $estado = $certificado->estado instanceof EstadoCertificadoEnum
            ? $certificado->estado
            : EstadoCertificadoEnum::from($certificado->estado);

        $vigente = $estado->esVigenteParaValidacion();
        $mensaje = $vigente
            ? 'Certificado valido.'
            : 'El certificado existe, pero no se encuentra vigente.';

        $this->registrarAuditoria->execute(
            accion: 'validar_certificado_publico',
            modelo: 'Certificado',
            modeloId: $certificado->id,
            descripcion: "Validacion publica del certificado {$certificado->codigo_unico}.",
            metadata: ['valido' => $vigente],
        );

        $data = [
            'valido'           => $vigente,
            'codigo_unico'    => $certificado->codigo_unico,
            'fecha_generacion'=> $certificado->fecha_generacion?->toDateString(),
            'estado'          => $estado->value,
            'mensaje'         => $mensaje,
            'funcionario'     => $certificado->funcionario ? [
                'nombre' => trim($certificado->funcionario->nombres . ' ' . $certificado->funcionario->apellidos),
            ] : null,
        ];

        return $this->successResponse(new ValidacionCertificadoResource($data), $mensaje);
    }
}
