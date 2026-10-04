<?php

namespace Tests\Feature\Security;

use App\Services\SecurityConfiguration;

class HeadersSecurityTest extends SecurityTestCase
{
    public function test_security_headers_and_sensitive_cache_on_errors_and_public_responses(): void
    {
        foreach (['/api/v1/auth/me', '/api/v1/validar-certificado/invalid'] as $url) {
            $response = $this->getJson($url)->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Strict-Transport-Security');
            $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
            $this->assertStringNotContainsString('unsafe-eval', $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_cookies_http_only_lax_and_secure_flag_configurable(): void
    {
        config(['session.secure' => true]);
        $response = $this->get('/sanctum/csrf-cookie')->assertNoContent();
        $session = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($session->isHttpOnly()); $this->assertTrue($session->isSecure()); $this->assertSame('lax', $session->getSameSite());
    }

    public function test_production_debug_and_default_database_fail_closed_without_secret_output(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);
        $issues = app(SecurityConfiguration::class)->critical();
        $this->assertContains('APP_DEBUG_ENABLED', $issues);
        $this->assertContains('DB_DEFAULT_USER', $issues);
        $this->getJson('/api/v1/auth/me')->assertStatus(503)->assertJsonPath('code', 'SECURITY_CONFIGURATION_INVALID');
        $this->artisan('security:check-config')->assertExitCode(1);
    }

    public function test_production_https_sets_hsts_when_configuration_valid(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.debug' => false, 'app.url' => 'https://api.example.test', 'session.secure' => true, 'session.encrypt' => true,
            'session.lifetime' => 30, 'session.driver' => 'file', 'cors.allowed_origins' => ['https://app.example.test'],
            'sanctum.stateful' => ['app.example.test'], 'database.connections.pgsql.username' => 'scl_runtime',
            'database.connections.pgsql.password' => 'synthetic-password-not-a-deployment-secret', 'hashing.driver' => 'argon2id']);
        $this->assertSame([], app(SecurityConfiguration::class)->critical());
        $this->get('https://api.example.test/up', ['Origin' => 'https://app.example.test'])->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_production_checker_rejects_additional_known_e2e_accounts_without_credentials_in_output(): void
    {
        $fixture = \App\Models\User::factory()->backoffice()->create(['username' => 'e2e-secretaria-2']);
        $fixture->assignRole('secretario');
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('security:check-config', ['--accounts' => true])
            ->expectsOutput('CRITICAL: SYNTHETIC_ACCOUNTS_PRESENT')->assertExitCode(1);
    }
}
