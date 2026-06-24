<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRangoSalarialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rangos_salariales.editar');
    }

    public function rules(): array
    {
        $id = $this->route('rango_salarial');

        return [
            'codigo'         => ['sometimes', 'string', 'max:10'],
            'grado'          => ['sometimes', 'string', 'max:5'],
            'denominacion'   => ['nullable', 'string', 'max:200'],
            'vigencia_anio'  => [
                'sometimes',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('rangos_salariales')->where(function ($query) {
                    return $query->where('codigo', $this->codigo)
                                 ->where('grado', $this->grado);
                })->ignore($id),
            ],
            'salario_basico' => ['sometimes', 'numeric', 'min:0'],
            'moneda'         => ['sometimes', 'string', 'size:3'],
            'observaciones'  => ['nullable', 'string'],
            'estado'         => ['boolean'],
        ];
    }
}
