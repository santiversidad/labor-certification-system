<?php

namespace App\Http\Requests;

use App\Models\RangoSalarial;
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
        $rango = RangoSalarial::find($id);
        $codigo = $this->input('codigo', $rango?->codigo);
        $grado = $this->input('grado', $rango?->grado);
        $vigencia = $this->input('vigencia_anio', $rango?->vigencia_anio);

        return [
            'codigo' => ['sometimes', 'string', 'max:10',
                Rule::unique('rangos_salariales', 'codigo')
                    ->where(fn ($query) => $query->where('grado', $grado)->where('vigencia_anio', $vigencia))
                    ->ignore($id),
            ],
            'grado' => ['sometimes', 'string', 'max:5',
                Rule::unique('rangos_salariales', 'grado')
                    ->where(fn ($query) => $query->where('codigo', $codigo)->where('vigencia_anio', $vigencia))
                    ->ignore($id),
            ],
            'vigencia_anio' => [
                'sometimes',
                'integer',
                'min:2000',
                'max:2100',
                Rule::unique('rangos_salariales', 'vigencia_anio')
                    ->where(fn ($query) => $query->where('codigo', $codigo)->where('grado', $grado))
                    ->ignore($id),
            ],
            'salario_basico' => ['sometimes', 'numeric', 'min:0'],
            'moneda' => ['sometimes', 'string', 'size:3'],
            'observaciones' => ['nullable', 'string'],
            'estado' => ['boolean'],
        ];
    }
}
