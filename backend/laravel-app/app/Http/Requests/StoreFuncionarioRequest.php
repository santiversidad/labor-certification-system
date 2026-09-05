<?php

namespace App\Http\Requests;

use App\Enums\EstadoFuncionarioEnum;
use App\Enums\NaturalezaCargoEnum;
use App\Enums\TipoVinculacionEnum;
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
            'user_id' => ['prohibited'],
            'manual_cargo_version_id' => ['nullable', 'integer', 'exists:manual_cargo_versiones,id'],
            'es_prueba_manual' => ['prohibited'],
            'tipo_documento' => ['required', 'string', Rule::in(['CC', 'CE', 'PA', 'TI'])],
            'numero_documento' => ['required', 'string', 'max:20', 'unique:funcionarios,numero_documento', 'unique:users,documento'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo_institucional' => ['nullable', 'email', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'estado' => ['sometimes', Rule::enum(EstadoFuncionarioEnum::class)],
            'fecha_ingreso' => ['required', 'date'],
            'fecha_retiro' => ['nullable', 'date', 'after_or_equal:fecha_ingreso'],
            'dependencia' => ['nullable', 'string', 'max:150'],
            'cargo_id' => ['required', 'exists:cargos,id'],
            'tipo_vinculacion' => ['required', Rule::enum(TipoVinculacionEnum::class)],
            'naturaleza_cargo' => ['required', Rule::enum(NaturalezaCargoEnum::class)],
        ];
    }
}
