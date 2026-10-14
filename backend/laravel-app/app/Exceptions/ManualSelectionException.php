<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManualSelectionException extends ValidationException
{
    public string $domainCode;

    public static function forCargo(string $code, string $message): self
    {
        $exception = static::withMessages(['cargo_id' => $message]);
        $exception->domainCode = $code;

        return $exception;
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Los datos suministrados no son válidos.',
            'errors' => $this->errors(),
            'code' => $this->domainCode,
        ], 422);
    }
}
