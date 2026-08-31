<?php

namespace App\Http\Requests;

use App\Enums\EstadoCertificadoEnum;
use App\Enums\EstadoFuncionarioEnum;
use App\Enums\EstadoPagoEnum;
use App\Enums\EstadoSolicitudEnum;
use App\Enums\TipoActuacionAdministrativaEnum;
use App\Enums\TipoCertificadoEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeName = (string) $this->route()?->getName();
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'direccion' => ['sometimes', Rule::in(['asc', 'desc'])],
            'q' => ['sometimes', 'string', 'max:200'],
        ];

        return match ($routeName) {
            'v1.cargos.index' => $rules + [
                'estado' => ['sometimes', 'boolean'],
                'orden' => ['sometimes', Rule::in(['denominacion', 'codigo', 'grado', 'created_at'])],
            ],
            'v1.rangos-salariales.index' => $rules + [
                'vigencia' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
                'codigo' => ['sometimes', 'string', 'max:10'],
                'estado' => ['sometimes', 'boolean'],
                'orden' => ['sometimes', Rule::in(['vigencia_anio', 'codigo', 'grado', 'salario_basico', 'created_at'])],
            ],
            'v1.funcionarios.index' => $rules + [
                'estado' => ['sometimes', Rule::enum(EstadoFuncionarioEnum::class)],
                'dependencia' => ['sometimes', 'string', 'max:150'],
                'cargo_id' => ['sometimes', 'integer', 'min:1', 'exists:cargos,id'],
                'orden' => ['sometimes', Rule::in(['apellidos', 'nombres', 'numero_documento', 'created_at'])],
            ],
            'v1.solicitudes.index' => $rules + [
                'estado' => ['sometimes', Rule::enum(EstadoSolicitudEnum::class)],
                'tipo_certificado' => ['sometimes', Rule::enum(TipoCertificadoEnum::class)],
                'funcionario_id' => ['sometimes', 'integer', 'min:1', 'exists:funcionarios,id'],
                'fecha_desde' => ['sometimes', 'date'],
                'fecha_hasta' => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
                'orden' => ['sometimes', Rule::in(['created_at', 'radicado', 'estado', 'periodo_mes'])],
            ],
            'v1.pagos.index' => $rules + [
                'estado' => ['sometimes', Rule::enum(EstadoPagoEnum::class)],
                'funcionario_id' => ['sometimes', 'integer', 'min:1', 'exists:funcionarios,id'],
                'orden' => ['sometimes', Rule::in(['created_at', 'estado', 'validado_at'])],
            ],
            'v1.certificados.index' => $rules + [
                'estado' => ['sometimes', Rule::enum(EstadoCertificadoEnum::class)],
                'orden' => ['sometimes', Rule::in(['created_at', 'fecha_generacion', 'codigo_unico', 'estado'])],
            ],
            'v1.auditoria.index' => $rules + [
                'usuario' => ['sometimes', 'integer', 'min:1'],
                'user_id' => ['sometimes', 'integer', 'min:1'],
                'accion' => ['sometimes', 'string', 'max:80'],
                'entidad' => ['sometimes', 'string', 'max:100'],
                'modelo' => ['sometimes', 'string', 'max:100'],
                'fecha_desde' => ['sometimes', 'date'],
                'fecha_hasta' => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
                'orden' => ['sometimes', Rule::in(['created_at', 'accion', 'modelo', 'user_id'])],
            ],
            'v1.actuaciones.index' => $rules + [
                'funcionario_id' => ['sometimes', 'integer', 'min:1', 'exists:funcionarios,id'],
                'tipo_actuacion' => ['sometimes', Rule::enum(TipoActuacionAdministrativaEnum::class)],
                'orden' => ['sometimes', Rule::in(['created_at', 'fecha_acto', 'tipo_actuacion'])],
            ],
            'v1.manual-funciones.index' => $rules + [
                'orden' => ['sometimes', Rule::in(['nombre', 'codigo', 'created_at'])],
            ],
            default => $rules,
        };
    }

    public function messages(): array
    {
        return [
            'per_page.max' => 'El tamaño máximo de página es 100.',
            'direccion.in' => 'La dirección de orden debe ser asc o desc.',
            'orden.in' => 'El campo de ordenamiento no está permitido.',
        ];
    }
}
