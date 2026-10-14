<?php

namespace Tests\Feature\Security;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

class SqlInjectionSecurityTest extends SecurityTestCase
{
    public static function payloads(): array { return [["' OR 1=1 --"], ['"; DROP TABLE users; --']]; }

    #[DataProvider('payloads')]
    public function test_search_values_are_bound_and_order_columns_allowlisted(string $payload): void
    {
        $admin = $this->account(); $before = User::count();
        $this->actingAs($admin, 'sanctum');
        foreach (['funcionarios', 'cargos', 'manual-funciones', 'admin/usuarios-internos'] as $path) {
            $this->getJson('/api/v1/'.$path.'?q='.urlencode($payload))->assertOk()->assertJsonPath('meta.total', 0);
        }
        foreach (['funcionarios', 'cargos', 'auditoria', 'certificados', 'solicitudes', 'manual-funciones'] as $path) {
            $this->getJson('/api/v1/'.$path.'?orden='.urlencode($payload))->assertUnprocessable();
        }
        $this->assertSame($before, User::count());
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
