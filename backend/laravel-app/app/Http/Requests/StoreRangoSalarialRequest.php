<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRangoSalarialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rangos_salariales.crear');
    }

    public function rules(): array
    {
        return [
            'codigo'         => ['required', 'string', 'max:10'],
            'grado'          => ['required', 'string', 'max:5'],
            'denominacion'   => ['nullable', 'string', 'max:200'],
            'vigencia_anio'  => [
                'required',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('rangos_salariales')->where(function ($query) {
                    return $query->where('codigo', $this->codigo)
                                 ->where('grado', $this->grado);
                }),
            ],
            'salario_basico' => ['required', 'numeric', 'min:0'],
            'moneda'         => ['sometimes', 'string', 'size:3'],
            'observaciones'  => ['nullable', 'string'],
            'estado'         => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'vigencia_anio.unique' => 'Ya existe un rango salarial para ese código, grado y vigencia.',
        ];
    }
}
