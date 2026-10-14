<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParametroPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('parametros.editar');
    }

    public function rules(): array
    {
        return ['requiere_pago_certificado' => ['required', 'boolean']];
    }
}
