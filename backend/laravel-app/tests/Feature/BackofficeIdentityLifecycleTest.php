<?php

namespace Tests\Feature;

use App\Exceptions\IdentityException;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\AuditLog;
use App\Models\Funcionario;
use App\Models\User;
use App\Services\BackofficeIdentityService;
use Database\Seeders\DatosEjemploSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\UsuariosInicialesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackofficeIdentityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->admin = $this->internal('admin');
    }

    private function internal(string $role): User
    {
        $user = User::factory()->backoffice()->create(['password' => 'PruebaClaveNueva2026', 'must_change_password' => false]);
        $user->syncRoles([$role]);

        return $user->fresh();
    }

    private function employee(): User
    {
        $user = User::factory()->create(['password' => 'EmployeeClave2026', 'must_change_password' => false]);
        $user->assignRole('funcionario');
        Funcionario::create(['user_id' => $user->id, 'numero_documento' => $user->documento,
            'nombres' => 'María', 'apellidos' => 'Test', 'estado' => 'activo']);

        return $user->fresh();
    }

    private function login(User $user, string $password = 'PruebaClaveNueva2026'): string
    {
        Auth::forgetGuards();
        $this->postJson('/api/v1/auth/login', ['username' => strtoupper($user->username), 'password' => $password])
            ->assertOk()->assertJsonMissingPath('data.token');
        // Preserve non-browser token revocation regressions using explicit test-only issuance.
        $token = $user->fresh()->createToken('cli-regression', ['*'])->plainTextToken;
        Auth::forgetGuards();

        return $token;
    }

    private function tokenGet(string $token, string $path)
    {
        Auth::forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/'.$path);
    }

    public static function roles(): array
    {
        return [['admin'], ['secretario']];
    }

    #[DataProvider('roles')]
    public function test_creation_generates_individual_backoffice_without_employee(string $role): void
    {
        $before = Funcionario::count();
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos',
            ['name' => 'María Test', 'username' => 'Maria.Test', 'role' => $role])
            ->assertCreated()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.user.tipo_cuenta', 'backoffice')->assertJsonPath('data.user.username', 'maria.test')
            ->assertJsonPath('data.user.funcionario_id', null)->assertJsonPath('data.user.documento', null)
            ->assertJsonPath('data.user.must_change_password', true);
        $user = User::findOrFail($response->json('data.user.id'));
        $password = $response->json('data.temporary_password');
        $this->assertGreaterThanOrEqual(16, strlen($password));
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertSame($before, Funcionario::count());
        $this->assertStringNotContainsString($password, AuditLog::all()->toJson());
        $this->assertStringNotContainsString($password, $user->toJson());
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/usuarios-internos/'.$user->id)
            ->assertOk()->assertJsonMissingPath('data.temporary_password')->assertJsonMissingPath('data.password');
    }

    public static function invalidCreation(): array
    {
        return [
            [['username' => '123456789'], null],
            [['username' => 'ab'], null],
            [['username' => 'con espacio'], null],
            [['username' => 'admin'], 'INDIVIDUAL_USERNAME_REQUIRED'],
            [['role' => 'funcionario'], 'INVALID_ACCOUNT_ROLE'],
            [['funcionario_id' => 1], 'ACCOUNT_CONTEXT_MISMATCH'],
            [['documento' => '123456'], 'ACCOUNT_CONTEXT_MISMATCH'],
            [['roles' => ['admin', 'funcionario']], 'ACCOUNT_CONTEXT_MISMATCH'],
        ];
    }

    #[DataProvider('invalidCreation')]
    public function test_invalid_creation_is_rejected(array $extra, ?string $code): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos',
            array_replace(['name' => 'Persona Test', 'username' => 'persona.test', 'role' => 'secretario'], $extra))
            ->assertUnprocessable();
        if ($code) {
            $response->assertJsonPath('code', $code);
        }
    }

    public function test_duplicate_username_is_normalized_and_rejected(): void
    {
        app(BackofficeIdentityService::class)->create(['name' => 'Persona A', 'username' => 'maria.test', 'role' => 'secretario']);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos',
            ['name' => 'Persona B', 'username' => ' MARIA.TEST ', 'role' => 'admin'])
            ->assertStatus(409)->assertJsonPath('code', 'BACKOFFICE_USERNAME_TAKEN');
    }

    public static function invalidRoleAssignments(): array
    {
        return [['funcionario', ['admin']], ['funcionario', ['secretario']], ['backoffice', ['funcionario']],
            ['funcionario', ['funcionario', 'admin']], ['backoffice', ['admin', 'secretario']]];
    }

    #[DataProvider('invalidRoleAssignments')]
    public function test_model_disallows_cross_context_and_mixed_roles(string $type, array $roles): void
    {
        $user = $type === 'funcionario' ? $this->employee() : $this->internal('secretario');
        $this->expectException(IdentityException::class);
        $this->expectExceptionMessage('El rol no corresponde');
        $user->syncRoles($roles);
    }

    public function test_employee_link_to_backoffice_is_rejected(): void
    {
        $this->expectException(IdentityException::class);
        Funcionario::create(['user_id' => $this->admin->id, 'numero_documento' => '100000001',
            'nombres' => 'María', 'apellidos' => 'Test']);
    }

    public function test_unlinked_employee_identity_cannot_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'EmployeeClave2026']);
        $user->assignRole('funcionario');
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'EmployeeClave2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_database_rejects_missing_employee_even_when_model_is_bypassed(): void
    {
        try {
            DB::transaction(function () {
                $user = User::factory()->create();
                $user->assignRole('funcionario');
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected topology violation');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
    }

    public function test_database_rejects_backoffice_link_when_model_is_bypassed(): void
    {
        try {
            DB::transaction(function () {
                DB::table('funcionarios')->insert(['user_id' => $this->admin->id, 'numero_documento' => '100000001',
                    'nombres' => 'María', 'apellidos' => 'Test', 'estado' => 'activo']);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Expected topology violation');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
    }

    #[DataProvider('roles')]
    public function test_temporal_requires_password_change_before_business_access(string $role): void
    {
        $result = app(BackofficeIdentityService::class)->create(['name' => 'María Test', 'username' => 'maria.test', 'role' => $role]);
        $user = $result['user'];
        $token = $this->login($user, $result['temporary_password']);
        $path = $role === 'admin' ? 'admin/usuarios-internos' : 'secretaria/pagos';
        $this->tokenGet($token, $path)->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        Auth::forgetGuards();
        $this->withToken($token)->putJson('/api/v1/auth/change-password', [
            'current_password' => $result['temporary_password'], 'password' => 'NuevaClavePersonal2026',
            'password_confirmation' => 'NuevaClavePersonal2026',
        ])->assertOk()->assertJsonPath('data.must_change_password', false);
        $this->tokenGet($token, $path)->assertOk();
        $this->assertTrue(AuditLog::where('accion', 'forced_password_changed')->exists());
    }

    public function test_login_tracks_success_and_not_invalid_credentials(): void
    {
        $this->assertNull($this->admin->last_login_at);
        $this->postJson('/api/v1/auth/login', ['username' => $this->admin->username, 'password' => 'incorrecta'])->assertUnprocessable();
        $this->assertNull($this->admin->fresh()->last_login_at);
        $this->login($this->admin);
        $this->assertNotNull($this->admin->fresh()->last_login_at);
    }

    public function test_employee_login_by_document_remains_supported(): void
    {
        $user = $this->employee();
        $this->postJson('/api/v1/auth/login', ['cedula' => $user->documento, 'password' => 'EmployeeClave2026'])
            ->assertOk()->assertJsonPath('data.user.tipo_cuenta', 'funcionario');
    }

    public function test_deactivation_revokes_tokens_preserves_history_and_reactivation_never_revives(): void
    {
        $target = $this->internal('admin');
        $token = $this->login($target);
        $history = AuditLog::create(['user_id' => $target->id, 'accion' => 'historic_action', 'modelo' => 'User', 'modelo_id' => $target->id]);
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos/'.$target->id.'/desactivar',
            ['motivo' => 'Fin de vinculación ficticia'])->assertOk();
        $this->assertSame(0, $target->tokens()->count());
        $this->assertSame($target->id, $history->fresh()->user_id);
        $this->tokenGet($token, 'auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['username' => $target->username, 'password' => 'PruebaClaveNueva2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('username');
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos/'.$target->id.'/reactivar')->assertOk();
        $this->tokenGet($token, 'auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['username' => $target->username, 'password' => 'PruebaClaveNueva2026'])->assertUnprocessable();
        $this->login($target->fresh(), $response->json('data.temporary_password'));
        $this->assertTrue(AuditLog::where('accion', 'backoffice_deactivated')->where('user_id', $this->admin->id)->exists());
        $this->assertTrue(AuditLog::where('accion', 'backoffice_reactivated')->exists());
    }

    public function test_reset_revokes_old_credentials_and_never_audits_secret(): void
    {
        $target = $this->internal('secretario');
        $token = $this->login($target);
        $result = app(BackofficeIdentityService::class)->transition($target, 'reset', [], $this->admin);
        $password = $result['temporary_password'];
        $this->tokenGet($token, 'secretaria/pagos')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['username' => $target->username, 'password' => 'PruebaClaveNueva2026'])->assertUnprocessable();
        $newToken = $this->login($target->fresh(), $password);
        $this->tokenGet($newToken, 'secretaria/pagos')->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $logs = AuditLog::all()->toJson();
        $this->assertStringNotContainsString($password, $logs);
        $this->assertStringNotContainsString($target->fresh()->password, $logs);
        $this->assertStringNotContainsString($password, (string) @file_get_contents(storage_path('logs/laravel.log')));
    }

    public function test_role_change_revokes_and_applies_new_authority(): void
    {
        $target = $this->internal('admin');
        $token = $this->login($target);
        app(BackofficeIdentityService::class)->transition($target, 'role', ['role' => 'secretario'], $this->admin);
        $this->tokenGet($token, 'admin/usuarios-internos')->assertUnauthorized();
        $new = $this->login($target->fresh());
        $this->tokenGet($new, 'admin/usuarios-internos')->assertForbidden();
        $this->tokenGet($new, 'secretaria/pagos')->assertOk();
        $this->assertSame('admin', AuditLog::where('accion', 'backoffice_role_changed')->first()->metadata['previous_role']);
    }

    public function test_last_admin_cannot_be_deactivated_or_demoted(): void
    {
        foreach ([['desactivar', ['motivo' => 'Prueba fin de acceso']], ['cambiar-rol', ['role' => 'secretario']]] as [$action, $payload]) {
            $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos/'.$this->admin->id.'/'.$action, $payload)
                ->assertStatus(409)->assertJsonPath('code', 'LAST_ACTIVE_ADMIN');
        }
    }

    public function test_self_deactivation_and_self_demotion_are_blocked_with_two_admins(): void
    {
        $this->internal('admin');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos/'.$this->admin->id.'/desactivar',
            ['motivo' => 'Prueba de autodesactivación'])->assertStatus(409)->assertJsonPath('code', 'CANNOT_DEACTIVATE_SELF');
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/admin/usuarios-internos/'.$this->admin->id.'/cambiar-rol',
            ['role' => 'secretario'])->assertStatus(409)->assertJsonPath('code', 'CANNOT_CHANGE_OWN_ROLE');
    }

    public function test_internal_user_management_is_admin_only(): void
    {
        foreach ([$this->employee(), $this->internal('secretario')] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/usuarios-internos')->assertForbidden();
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/usuarios-internos', ['name' => 'Test', 'username' => 'nuevo.test', 'role' => 'admin'])->assertForbidden();
            foreach (['desactivar' => ['motivo' => 'Prueba ficticia'], 'reactivar' => [], 'reset-password' => [], 'cambiar-rol' => ['role' => 'secretario']] as $action => $data) {
                $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/usuarios-internos/'.$this->admin->id.'/'.$action, $data)->assertForbidden();
            }
            $this->actingAs($user, 'sanctum')->patchJson('/api/v1/admin/usuarios-internos/'.$this->admin->id, ['name' => 'Transferida'])->assertForbidden();
        }
    }

    #[DataProvider('roles')]
    public function test_backoffice_cannot_use_employee_selfservice_even_with_broad_permissions(string $role): void
    {
        $user = $this->internal($role);
        $user->givePermissionTo('solicitudes.crear');
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/mi-certificacion/disponibilidad')
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_CONTEXT_MISMATCH');
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/solicitudes', ['tipo_certificado' => 'sencillo'])
            ->assertForbidden()->assertJsonPath('code', 'ACCOUNT_CONTEXT_MISMATCH');
    }

    public function test_two_accounts_for_one_person_have_independent_tokens_credentials_and_context(): void
    {
        $employee = $this->employee();
        $internal = $this->internal('admin');
        $internal->update(['name' => 'María Test']);
        $employeeToken = $this->login($employee, 'EmployeeClave2026');
        $internalToken = $this->login($internal);
        $this->assertNotSame($employee->id, $internal->id);
        $this->assertNotSame($employeeToken, $internalToken);
        $this->assertFalse(Hash::check('EmployeeClave2026', $internal->password));
        $this->tokenGet($employeeToken, 'mi-certificacion/disponibilidad')->assertOk();
        $this->tokenGet($internalToken, 'admin/usuarios-internos')->assertOk();
        $this->tokenGet($internalToken, 'mi-certificacion/disponibilidad')->assertForbidden();
        $this->tokenGet($employeeToken, 'admin/usuarios-internos')->assertForbidden();
    }

    public function test_username_is_immutable_and_deletion_prohibited(): void
    {
        $this->actingAs($this->admin, 'sanctum')->patchJson('/api/v1/admin/usuarios-internos/'.$this->admin->id,
            ['username' => 'otra.persona'])->assertUnprocessable();
        $this->expectException(IdentityException::class);
        $this->admin->delete();
    }

    public function test_cookie_epoch_rejects_old_session_after_reset_and_reactivation(): void
    {
        $target = $this->internal('secretario');
        $oldEpoch = $target->auth_version;
        $this->withHeader('Origin', 'http://localhost:3200')->actingAs($target, 'web')->withSession(['auth_version' => $oldEpoch])
            ->getJson('/api/v1/secretaria/pagos')->assertOk();
        app(BackofficeIdentityService::class)->transition($target, 'deactivate', ['motivo' => 'Fin prueba cookie'], $this->admin);
        app(BackofficeIdentityService::class)->transition($target->fresh(), 'reactivate', [], $this->admin);
        Auth::forgetGuards();
        $this->actingAs($target->fresh(), 'web')->withSession(['auth_version' => $oldEpoch])
            ->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_legacy_username_is_explicit_and_preserves_actor(): void
    {
        $id = DB::table('users')->insertGetId(['name' => 'Actor histórico', 'documento' => '9999999',
            'password' => Hash::make('HistoricaClave2026'), 'estado' => true, 'tipo_cuenta' => 'legacy']);
        $legacy = User::findOrFail($id);
        $result = app(BackofficeIdentityService::class)->provisionLegacy($legacy, 'persona.explicita', 'admin');
        $this->assertSame($id, $result['user']->id);
        $this->assertNull($result['user']->documento);
        $this->assertTrue($result['user']->isBackofficeAccount());
        $this->assertTrue(AuditLog::where('accion', 'backoffice_username_provisioned')->where('modelo_id', $id)->exists());
    }

    public function test_cookie_epoch_also_revokes_role_change_without_changing_password(): void
    {
        $target = $this->internal('admin');
        $epoch = $target->auth_version;
        $this->withHeader('Origin', 'http://localhost:3200')->actingAs($target, 'web')
            ->withSession(['auth_version' => $epoch])->getJson('/api/v1/auth/me')->assertOk();
        app(BackofficeIdentityService::class)->transition($target, 'role', ['role' => 'secretario'], $this->admin);
        Auth::forgetGuards();
        $this->actingAs($target->fresh(), 'web')->withSession(['auth_version' => $epoch])
            ->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'SESSION_REVOKED');
    }

    public function test_legacy_linked_actor_can_be_explicitly_split_without_erasing_history(): void
    {
        $employeeUser = $this->employee();
        $employee = $employeeUser->funcionario;
        DB::table('users')->where('id', $employeeUser->id)->update(['tipo_cuenta' => 'legacy']);
        $legacy = $employeeUser->fresh();
        app(BackofficeIdentityService::class)->provisionLegacy($legacy, 'maria.legacy.test', 'admin');
        $this->assertNull($employee->fresh()->user_id);
        $this->artisan('funcionario:provision-account', ['funcionario' => $employee->id])->assertSuccessful();
        $personal = $employee->fresh()->user;
        $this->assertNotSame($legacy->id, $personal->id);
        $this->assertTrue($personal->must_change_password);
        $this->assertTrue($personal->isFuncionarioAccount());
        $this->assertTrue($legacy->fresh()->isBackofficeAccount());
        $this->assertFalse(Hash::check($employee->numero_documento, $legacy->fresh()->password));
        $this->assertSame($employee->id, $personal->funcionario->id);
    }

    public function test_production_seeders_never_install_known_credentials(): void
    {
        $this->app->instance('env', 'production');
        $count = User::count();
        $this->artisan('db:seed', ['--class' => UsuariosInicialesSeeder::class, '--force' => true])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => DatosEjemploSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame($count, User::count());
        $this->assertFalse(User::whereIn('username', ['admin_test', 'secretario_test'])->exists());
    }

    public function test_database_requires_username_for_ready_accounts(): void
    {
        try {
            DB::transaction(fn () => DB::table('users')->where('id', $this->admin->id)->update(['username' => null]));
            $this->fail('Username cannot be NULL');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
    }

    public function test_list_uses_existing_root_pagination_and_server_filters(): void
    {
        $secretary = $this->internal('secretario');
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/admin/usuarios-internos?role=secretario&estado=1&q='.$secretary->username)
            ->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']])
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $secretary->id)
            ->assertJsonMissingPath('data.meta');
    }

    public function test_bootstrap_command_works_without_an_existing_active_admin(): void
    {
        $this->admin->update(['estado' => false]);
        $exit = Artisan::call('backoffice:provision-admin', [
            '--name' => 'Primer administrador ficticio', '--username' => 'primera.persona.test',
        ]);
        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $lines = array_values(array_filter(array_map('trim', explode("\n", $output))));
        $temporary = $lines[count($lines) - 1];
        $user = User::where('username', 'primera.persona.test')->firstOrFail();
        $this->assertGreaterThanOrEqual(16, strlen($temporary));
        $this->assertTrue(Hash::check($temporary, $user->password));
        $this->assertTrue($user->estado && $user->must_change_password && $user->isBackofficeAccount());
        $this->assertStringNotContainsString($temporary, AuditLog::all()->toJson());
        $this->assertSame(1, Artisan::call('backoffice:provision-admin', [
            '--name' => 'Otra persona ficticia', '--username' => 'primera.persona.test',
        ]));
        $this->assertStringContainsString('BACKOFFICE_USERNAME_TAKEN', Artisan::output());
    }

    public function test_password_change_cannot_revive_cookie_revoked_after_middleware(): void
    {
        $target = $this->internal('secretario');
        $epoch = $target->auth_version;
        $target->withAccessToken(new TransientToken);
        $request = ChangePasswordRequest::create('/api/v1/auth/change-password', 'PUT', [
            'current_password' => 'PruebaClaveNueva2026',
            'password' => 'OtraPersonalClave2026', 'password_confirmation' => 'OtraPersonalClave2026',
        ]);
        $request->setUserResolver(fn () => $target);
        $session = app('session')->driver();
        $session->put('auth_version', $epoch);
        $request->setLaravelSession($session);
        $request->setValidator(Validator::make($request->all(), $request->rules()));
        // The request passed middleware, then another administrator revoked this cookie.
        app(BackofficeIdentityService::class)->transition($target, 'role', ['role' => 'admin'], $this->admin);
        try {
            app(AuthController::class)->changePassword($request);
            $this->fail('A stale cookie must not renew its epoch');
        } catch (IdentityException $e) {
            $this->assertSame('SESSION_REVOKED', $e->domainCode);
        }
        $this->assertTrue(Hash::check('PruebaClaveNueva2026', $target->fresh()->password));
        $this->assertSame($epoch, $session->get('auth_version'));
    }
}
