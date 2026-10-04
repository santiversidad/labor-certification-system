<?php

return [
    'mfa_backoffice_required' => true,
    // Issuance is CLI-only. Browser origins never accept bearer fallback.
    'api_tokens_enabled' => env('SECURITY_API_TOKENS_ENABLED', false),
    'trusted_proxies' => array_filter(explode(',', env('TRUSTED_PROXIES', ''))),
    'pending_minutes' => 10,
    'absolute_minutes' => 480,
    'step_up_seconds' => 300,
    'json_max_bytes' => 65536,
    'csp_connect_origins' => array_filter(explode(',', env('CSP_CONNECT_ORIGINS', ''))),
    'common_passwords' => ['password', 'password123456', '123456789012', '123456789012345', 'qwertyuiop123', 'contraseña123', 'administrador', 'villavicencio', 'passwordpassword'],
];
