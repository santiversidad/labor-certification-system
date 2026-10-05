<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

class SalaryScaleException extends \RuntimeException
{
    public function __construct(public readonly string $domainCode, string $message)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'code' => $this->domainCode, 'message' => $this->getMessage()], 409);
    }
}
