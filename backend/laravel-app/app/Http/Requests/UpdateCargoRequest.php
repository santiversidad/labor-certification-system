<?php

namespace App\Http\Requests;

use App\Models\Cargo;
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
        $cargo = Cargo::find($cargoId);
        $grado = $this->input('grado', $cargo?->grado);

        return [
            'codigo' => ['sometimes', 'string', 'max:10',
                Rule::unique('cargos')->where('grado', $grado)->ignore($cargoId),
            ],
            'grado' => ['sometimes', 'string', 'max:5',
                Rule::unique('cargos', 'grado')->where('codigo', $this->input('codigo', $cargo?->codigo))->ignore($cargoId),
            ],
            'denominacion' => ['sometimes', 'string', 'max:150'],
            'nivel' => ['nullable', 'string', 'max:60'],
            'dependencia' => ['nullable', 'string', 'max:150'],
            'estado' => ['boolean'],
        ];
    }
}
