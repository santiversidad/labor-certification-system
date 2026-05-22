<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cargos.crear');
    }

    public function rules(): array
    {
        return [
            'codigo'      => ['required', 'string', 'max:10', Rule::unique('cargos')->where('grado', $this->grado)],
            'grado'       => ['required', 'string', 'max:5'],
            'denominacion' => ['required', 'string', 'max:150'],
            'nivel'       => ['nullable', 'string', 'max:60'],
            'dependencia' => ['nullable', 'string', 'max:150'],
            'estado'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Ya existe un cargo con ese código y grado.',
        ];
    }
}
