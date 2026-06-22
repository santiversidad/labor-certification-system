<?php

namespace App\Http\Requests;

use App\Enums\TipoActuacionAdministrativaEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActuacionAdministrativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actuaciones.editar');
    }

    public function rules(): array
    {
        return [
            'funcionario_id'  => ['sometimes', 'exists:funcionarios,id'],
            'tipo_actuacion' => ['sometimes', Rule::enum(TipoActuacionAdministrativaEnum::class)],
            'numero_acto'    => ['nullable', 'string', 'max:60'],
            'fecha_acto'     => ['nullable', 'date'],
            'descripcion'    => ['sometimes', 'string', 'max:2000'],
        ];
    }
}
