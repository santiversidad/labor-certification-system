<?php

namespace App\Exceptions;

class CertificateTemplateException extends \RuntimeException
{
    public function __construct(public readonly string $domainCode, string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
