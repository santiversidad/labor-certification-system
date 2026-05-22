<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cargos.editar');
    }

    public function rules(): array
    {
        $cargoId = $this->route('cargo');

        return [
            'codigo'      => ['sometimes', 'string', 'max:10',
                Rule::unique('cargos')->where('grado', $this->grado ?? '')->ignore($cargoId),
            ],
            'grado'       => ['sometimes', 'string', 'max:5'],
            'denominacion' => ['sometimes', 'string', 'max:150'],
            'nivel'       => ['nullable', 'string', 'max:60'],
            'dependencia' => ['nullable', 'string', 'max:150'],
            'estado'      => ['boolean'],
        ];
    }
}
