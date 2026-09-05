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
        return $this->user()->can('solicitudes.crear');
    }

    public function rules(): array
    {
        return [
            // Salario es una modalidad ortogonal y explicita. Los valores historicos
            // salario/laboral_salario se conservan en el enum, pero no se crean nuevos.
            'tipo_certificado' => ['required', Rule::in([
                TipoCertificadoEnum::Laboral->value,
                TipoCertificadoEnum::Funciones->value,
            ])],
            'requiere_salario' => ['required', 'boolean'],
            'funcionario_id'   => ['prohibited'],
            'observaciones'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_certificado.required' => 'Debe seleccionar el tipo de certificado.',
            'tipo_certificado.enum'     => 'El tipo de certificado seleccionado no es válido.',
            'tipo_certificado.in'       => 'El tipo de certificado seleccionado no es válido.',
            'requiere_salario.required' => 'Debe indicar expresamente si la certificación requiere salario.',
            'funcionario_id.prohibited' => 'El funcionario se determina a partir de la sesión autenticada.',
        ];
    }
}
