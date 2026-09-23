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

        if ($this->has('tipo') && ! $this->has('tipo_certificado')) {
            $data['tipo_certificado'] = $this->input('tipo');
        }

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
            'funcionario_id' => ['prohibited'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_certificado.required' => 'Debe seleccionar el tipo de certificado.',
            'tipo_certificado.in' => 'El tipo de certificado seleccionado no es válido.',
            'funcionario_id.prohibited' => 'El funcionario se determina a partir de la sesión autenticada.',
        ];
    }
}
