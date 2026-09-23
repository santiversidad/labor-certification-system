<?php

namespace App\Http\Requests;

use App\Enums\TipoCertificadoEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolicitudCertificacionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('observacion') && ! $this->has('observaciones')) {
            $data['observaciones'] = $this->input('observacion');
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function authorize(): bool
    {
        // Solo usuarios con permiso de crear solicitudes
        return $this->user()->can('solicitudes.crear') && $this->user()->hasRole('funcionario');
    }

    public function rules(): array
    {
        return [
            'tipo_certificado' => ['required', Rule::in([
                TipoCertificadoEnum::Sencillo->value,
                TipoCertificadoEnum::Funciones->value,
            ])],
            'requiere_salario' => ['prohibited'],
            'tipo' => ['prohibited'],
            'funcionario_id' => ['prohibited'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_certificado.required' => 'Debe seleccionar el tipo de certificado.',
            'tipo_certificado.in' => 'El tipo de certificado seleccionado no es válido.',
            'requiere_salario.prohibited' => 'El campo requiere_salario fue retirado; use tipo_certificado.',
            'tipo.prohibited' => 'Use el campo canónico tipo_certificado.',
            'funcionario_id.prohibited' => 'El funcionario se determina a partir de la sesión autenticada.',
        ];
    }
}
