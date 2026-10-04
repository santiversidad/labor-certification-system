<?php

namespace Tests\Feature\Security;

class CorsSecurityTest extends SecurityTestCase
{
    public function test_exact_origin_credentialed_preflight(): void
    {
        $this->options('/api/v1/auth/login', [], ['Origin' => 'http://localhost:3200', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'Content-Type,X-XSRF-TOKEN'])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3200')->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_untrusted_preflight_not_reflected(): void
    {
        $this->options('/api/v1/auth/login', [], ['Origin' => 'https://untrusted.invalid', 'Access-Control-Request-Method' => 'POST'])->assertForbidden()->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_methods_and_headers_are_allowlisted(): void
    {
        $this->assertNotContains('*', config('cors.allowed_headers'));
        $this->assertNotContains('*', config('cors.allowed_methods'));
        $this->assertNotContains('TRACE', config('cors.allowed_methods'));
        $this->assertSame([], config('cors.allowed_origins_patterns'));
    }
}
