<?php

namespace App\Http\Requests;

use App\Enums\TipoActuacionAdministrativaEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActuacionAdministrativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('actuaciones.crear');
    }

    public function rules(): array
    {
        return [
            'funcionario_id'  => ['required', 'exists:funcionarios,id'],
            'tipo_actuacion' => ['required', Rule::enum(TipoActuacionAdministrativaEnum::class)],
            'numero_acto'    => ['nullable', 'string', 'max:60'],
            'fecha_acto'     => ['nullable', 'date'],
            'descripcion'    => ['required', 'string', 'max:2000'],
        ];
    }
}
