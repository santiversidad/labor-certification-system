<?php

namespace App\Http\Requests;

use App\Enums\EstadoFuncionarioEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuncionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('funcionarios.crear');
    }

    public function rules(): array
    {
        return [
            'user_id'              => ['nullable', 'exists:users,id'],
            'tipo_documento'       => ['required', 'string', Rule::in(['CC', 'CE', 'PA', 'TI'])],
            'numero_documento'     => ['required', 'string', 'max:20', 'unique:funcionarios,numero_documento'],
            'nombres'              => ['required', 'string', 'max:100'],
            'apellidos'            => ['required', 'string', 'max:100'],
            'correo_institucional' => ['nullable', 'email', 'max:150'],
            'telefono'             => ['nullable', 'string', 'max:20'],
            'estado'               => ['sometimes', Rule::enum(EstadoFuncionarioEnum::class)],
            'fecha_ingreso'        => ['nullable', 'date'],
            'fecha_retiro'         => ['nullable', 'date', 'after_or_equal:fecha_ingreso'],
            'dependencia'          => ['nullable', 'string', 'max:150'],
            'cargo_id'             => ['nullable', 'exists:cargos,id'],
        ];
    }
}
