<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pagos.validar') || $this->user()->can('pagos.rechazar');
    }

    public function rules(): array
    {
        return [
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }
}
