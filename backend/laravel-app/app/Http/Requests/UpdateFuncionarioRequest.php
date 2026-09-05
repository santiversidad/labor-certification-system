<?php

namespace App\Http\Requests;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\TipoVinculacionEnum;
use App\Models\Funcionario;
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
        $userId = Funcionario::find($funcionarioId)?->user_id;

        return [
            'user_id' => ['prohibited'],
            'manual_cargo_version_id' => ['nullable', 'integer', 'exists:manual_cargo_versiones,id'],
            'es_prueba_manual' => ['prohibited'],
            'tipo_documento' => ['sometimes', 'string', Rule::in(['CC', 'CE', 'PA', 'TI'])],
            'numero_documento' => [
                'sometimes', 'string', 'max:20',
                Rule::unique('funcionarios', 'numero_documento')->ignore($funcionarioId),
                Rule::unique('users', 'documento')->ignore($userId),
            ],
            'nombres' => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:100'],
            'correo_institucional' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'estado' => ['sometimes', Rule::enum(EstadoFuncionarioEnum::class)],
            'fecha_ingreso' => ['nullable', 'date'],
            'fecha_retiro' => ['nullable', 'date', 'after_or_equal:fecha_ingreso'],
            'dependencia' => ['nullable', 'string', 'max:150'],
            'cargo_id' => ['sometimes', 'required', 'integer', 'exists:cargos,id'],
            'tipo_vinculacion' => ['sometimes', Rule::enum(TipoVinculacionEnum::class)],
            'naturaleza_cargo' => ['sometimes', Rule::enum(NaturalezaCargoEnum::class)],
        ];
    }
}
