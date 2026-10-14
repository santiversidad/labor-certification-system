<?php

namespace Tests\Feature\Security;

use App\Services\SecurityEvents;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class InformationDisclosureTest extends SecurityTestCase
{
    public function test_500_database_and_not_found_errors_do_not_disclose_details(): void
    {
        Route::get('api/v1/security-test-error', fn () => throw new \RuntimeException('SQLSTATE password=synthetic C:\\private\\file.php'))->middleware('api');
        $response = $this->getJson('/api/v1/security-test-error')->assertStatus(500);
        foreach (['SQLSTATE', 'password=', 'private', 'stack', 'RuntimeException'] as $text) { $this->assertStringNotContainsString($text, $response->getContent()); }
        Route::get('api/v1/security-test-database-error', fn () => throw new \Illuminate\Database\QueryException(
            'pgsql', 'select * from private_records where password = ?', ['synthetic-private-binding'],
            new \PDOException('SQLSTATE[XX000]: synthetic controlled database failure')
        ))->middleware('api');
        $database = $this->getJson('/api/v1/security-test-database-error')->assertStatus(500);
        foreach (['SQLSTATE', 'private_records', 'synthetic-private-binding', 'QueryException', 'PDOException', 'trace'] as $text) {
            $this->assertStringNotContainsString($text, $database->getContent());
        }
        $this->getJson('/api/v1/not-a-route')->assertNotFound()->assertJsonMissingPath('exception');
    }

    public function test_security_log_accepts_only_allowlisted_fields_and_strips_newlines(): void
    {
        $channel = \Mockery::mock();
        Log::shouldReceive('channel')->once()->with('security')->andReturn($channel);
        $channel->shouldReceive('info')->once()->with('login_failed', ['user_id' => '5', 'reason' => 'badforged']);
        SecurityEvents::record('login_failed', ['user_id' => 5, 'reason' => "bad\r\nforged", 'password' => 'never-log', 'code' => '123456', 'cookie' => 'secret']);
    }

    public function test_json_payload_limit_rejects_before_validation(): void
    {
        $this->postJson('/api/v1/auth/login', ['username' => 'none', 'password' => str_repeat('x', 70000)])->assertStatus(413);
    }
}
