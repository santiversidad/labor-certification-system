<?php

namespace App\Http\Requests;

use App\Enums\EstadoSolicitudEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('solicitudes.cambiar_estado');
    }

    public function rules(): array
    {
        return [
            'estado'          => ['required', Rule::enum(EstadoSolicitudEnum::class)],
            'motivo_rechazo'  => [
                'nullable',
                'string',
                'max:500',
                // Obligatorio solo si se está rechazando
                Rule::requiredIf(
                    fn () => $this->estado === EstadoSolicitudEnum::Rechazado->value
                ),
            ],
            'observaciones'   => ['nullable', 'string', 'max:500'],
            'requiere_pago'   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required'         => 'Debe indicar el nuevo estado.',
            'estado.enum'             => 'El estado indicado no es válido.',
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
        ];
    }
}
