<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagoSoporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pagos.cargar');
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                // Solo formatos seguros y esperados para soportes de pago
                'mimes:pdf,jpg,jpeg,png',
                // Máximo 5 MB
                'max:5120',
            ],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe adjuntar el soporte de pago.',
            'archivo.file'     => 'El soporte debe ser un archivo válido.',
            'archivo.mimes'    => 'El soporte debe ser PDF, JPG o PNG.',
            'archivo.max'      => 'El archivo no puede superar los 5 MB.',
        ];
    }
}
