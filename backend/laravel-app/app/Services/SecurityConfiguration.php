<?php

namespace App\Services;

class SecurityConfiguration
{
    /** Stable codes only: never print configuration values or secrets. */
    public function critical(): array
    {
        if (! app()->environment('production')) {
            return [];
        }
        $issues = [];
        $check = function (bool $valid, string $code) use (&$issues) { if (! $valid) { $issues[] = $code; } };
        $check(! config('app.debug'), 'APP_DEBUG_ENABLED');
        $check((bool) config('app.key'), 'APP_KEY_MISSING');
        $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL_HTTPS_REQUIRED');
        $check((bool) config('session.secure') && (bool) config('session.http_only'), 'COOKIE_FLAGS_UNSAFE');
        $check(config('session.same_site') === 'lax' || (config('session.same_site') === 'strict'), 'COOKIE_SAMESITE_UNSAFE');
        $check(in_array(config('session.driver'), ['file', 'database', 'redis'], true), 'SESSION_DRIVER_UNSAFE');
        $check(config('session.encrypt') && config('session.lifetime') <= 30, 'SESSION_POLICY_UNSAFE');
        $origins = config('cors.allowed_origins', []);
        $check(count($origins) > 0 && ! array_filter($origins, fn ($url) => ! preg_match('~^https://[a-z0-9.-]+(?::\d+)?$~i', $url)
            || str_contains($url, 'localhost')), 'CORS_ORIGINS_UNSAFE');
        $check(config('cors.supports_credentials') && config('cors.allowed_origins_patterns') === [], 'CORS_POLICY_UNSAFE');
        $stateful = config('sanctum.stateful', []);
        $check(count($stateful) > 0 && ! array_filter($stateful, fn ($domain) => ! preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $domain)
            || str_contains($domain, 'localhost')), 'STATEFUL_DOMAINS_UNSAFE');
        foreach ($origins as $origin) {
            $host = parse_url($origin, PHP_URL_HOST);
            $port = parse_url($origin, PHP_URL_PORT);
            $check(in_array($host.($port ? ':'.$port : ''), $stateful, true), 'CORS_STATEFUL_MISMATCH');
        }
        $check(! in_array('*', config('security.trusted_proxies', []), true), 'TRUST_ALL_PROXIES');
        $check(config('filesystems.disks.local.root') === storage_path('app/private'), 'PRIVATE_STORAGE_REQUIRED');
        $check(! is_link(public_path('storage')), 'PUBLIC_STORAGE_LINK_PRESENT');
        $check(config('hashing.driver') === 'argon2id', 'HASH_DRIVER_UNSAFE');
        $check((int) config('hashing.argon.memory') >= 19456 && (int) config('hashing.argon.memory') <= 65536
            && (int) config('hashing.argon.time') >= 2 && (int) config('hashing.argon.time') <= 4, 'HASH_COST_UNSAFE');
        $check(config('security.mfa_backoffice_required') === true, 'MFA_POLICY_UNSAFE');
        $check(config('security.api_tokens_enabled') === false, 'API_TOKENS_REVIEW_REQUIRED');
        $connection = config('database.default');
        $check(! in_array(config("database.connections.$connection.username"), ['postgres', 'root', 'admin'], true), 'DB_DEFAULT_USER');
        $check(! in_array(config("database.connections.$connection.password"), ['', null, 'postgres', 'password', 'root'], true), 'DB_DEFAULT_CREDENTIALS');
        return array_values(array_unique($issues));
    }
}
