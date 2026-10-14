<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RechazarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('solicitudes.rechazar');
    }

    public function rules(): array
    {
        return [
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
        ];
    }
}
