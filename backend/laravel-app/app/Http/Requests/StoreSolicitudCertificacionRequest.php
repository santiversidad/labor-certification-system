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

        if ($this->has('incluyeSalario') && ! $this->has('requiere_salario')) {
            $data['requiere_salario'] = $this->input('incluyeSalario');
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
        return $this->user()->can('solicitudes.crear');
    }

    public function rules(): array
    {
        return [
            'tipo_certificado' => ['required', Rule::enum(TipoCertificadoEnum::class)],
            'requiere_salario' => ['boolean'],
            'observaciones'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_certificado.required' => 'Debe seleccionar el tipo de certificado.',
            'tipo_certificado.enum'     => 'El tipo de certificado seleccionado no es válido.',
        ];
    }
}
