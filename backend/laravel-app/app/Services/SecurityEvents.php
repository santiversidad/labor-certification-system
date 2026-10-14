<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SecurityEvents
{
    public static function record(string $event, array $context = []): void
    {
        // No arbitrary request data or free-text values may enter this channel.
        $safe = array_intersect_key($context, array_flip(['user_id', 'target_user_id', 'reason', 'ip_hash', 'identity_hash', 'request_id', 'comprobante_id']));
        $safe = array_map(fn ($value) => is_scalar($value) ? substr(str_replace(["\r", "\n"], '', (string) $value), 0, 128) : null, $safe);
        Log::channel('security')->info($event, $safe);
    }
}
