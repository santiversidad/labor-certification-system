<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ((string) $value === (string) $this->user()->documento) {
                        $fail('La nueva contraseña no puede ser igual a la cédula.');
                    }
                    if (Hash::check((string) $value, $this->user()->password)) {
                        $fail('La nueva contraseña debe ser diferente de la contraseña actual.');
                    }
                },
            ],
        ];
    }
}
