<?php

namespace App\Http\Requests;

use App\Enums\EstadoFuncionarioEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('funcionarios.editar');
    }

    public function rules(): array
    {
        $funcionarioId = $this->route('funcionario');

        return [
            'user_id'              => ['nullable', 'exists:users,id'],
            'tipo_documento'       => ['sometimes', 'string', Rule::in(['CC', 'CE', 'PA', 'TI'])],
            'numero_documento'     => [
                'sometimes', 'string', 'max:20',
                Rule::unique('funcionarios', 'numero_documento')->ignore($funcionarioId),
            ],
            'nombres'              => ['sometimes', 'string', 'max:100'],
            'apellidos'            => ['sometimes', 'string', 'max:100'],
            'correo_institucional' => ['nullable', 'email', 'max:150'],
            'telefono'             => ['nullable', 'string', 'max:20'],
            'estado'               => ['sometimes', Rule::enum(EstadoFuncionarioEnum::class)],
            'fecha_ingreso'        => ['nullable', 'date'],
            'fecha_retiro'         => ['nullable', 'date'],
            'dependencia'          => ['nullable', 'string', 'max:150'],
            'cargo_id'             => ['nullable', 'exists:cargos,id'],
        ];
    }
}
