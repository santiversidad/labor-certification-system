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
            'current_password' => ['required', 'string', 'max:128'],
            'password' => [
                'required',
                'confirmed',
                'string',
                'max:128',
                Password::min($this->user()->isBackofficeAccount() && ! $this->user()->mfa_confirmed_at ? 15 : 12),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array(mb_strtolower(trim((string) $value)), config('security.common_passwords'), true)) {
                        $fail('Elija una contraseña menos común.');
                    }
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
