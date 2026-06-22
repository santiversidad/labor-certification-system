<?php

namespace App\Http\Requests;

use App\Enums\EstadoSolicitudEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEstadoSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('solicitudes.cambiar_estado');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('estado')) {
            try {
                $this->merge([
                    'estado' => EstadoSolicitudEnum::fromInput((string) $this->estado)->value,
                ]);
            } catch (\ValueError) {
                // La regla enum devolvera el error de validacion correspondiente.
            }
        }
    }

    public function rules(): array
    {
        return [
            'estado'         => ['required', Rule::enum(EstadoSolicitudEnum::class)],
            'motivo_rechazo' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn () => $this->estado === EstadoSolicitudEnum::Rechazada->value),
            ],
            'observaciones'  => ['nullable', 'string', 'max:500'],
            'requiere_pago'  => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required'         => 'Debe indicar el nuevo estado.',
            'estado.enum'             => 'El estado indicado no es valido.',
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
        ];
    }
}
