<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certificados.anular');
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo de anulacion.',
        ];
    }
}
