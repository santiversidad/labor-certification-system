<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnforceCsrfToken;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;

class CsrfSecurityTest extends SecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(EnforceCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends EnforceCsrfToken
        {
            protected function runningUnitTests() { return false; }
        });
        Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], 'api/v1/security-test-csrf', fn () => response()->json(['ok' => true]))->middleware('api');
    }

    public static function methods(): array { return [['POST'], ['PUT'], ['PATCH'], ['DELETE']]; }

    #[DataProvider('methods')]
    public function test_all_mutating_methods_require_official_token_even_same_origin(string $method): void
    {
        $this->withSession(['_token' => 'synthetic-csrf-token']);
        $this->json($method, '/api/v1/security-test-csrf', [], ['Sec-Fetch-Site' => 'same-origin'])->assertStatus(419);
        $this->json($method, '/api/v1/security-test-csrf', [], ['X-CSRF-TOKEN' => 'synthetic-csrf-token'])->assertOk();
    }

    public function test_login_without_csrf_rejected_and_bootstrap_cookie_is_not_session_credential(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $user = $this->account('funcionario');
        $this->loginBrowser($user)->assertStatus(419);
    }
}
