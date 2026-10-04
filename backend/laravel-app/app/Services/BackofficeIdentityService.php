<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Exceptions\IdentityException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BackofficeIdentityService
{
    public function __construct(private readonly RegistrarAuditoriaAction $audit) {}

    public function create(array $data, ?User $actor = null): array
    {
        $this->authorize($actor, 'create');
        $data['username'] = Str::lower(trim((string) ($data['username'] ?? '')));
        $this->validate($data, true);

        try {
            return DB::transaction(function () use ($data, $actor) {
                $this->lockLifecycle();
                $this->validate($data, true);
                if ($actor) {
                    $this->authorize($actor->fresh(), 'create');
                }
                if (User::where('username', $data['username'])->exists()) {
                    throw new IdentityException('BACKOFFICE_USERNAME_TAKEN', 'Este usuario ya está registrado.');
                }
                $password = Str::password(24);
                $user = User::create([
                    'name' => $data['name'], 'username' => $data['username'], 'documento' => null,
                    'tipo_cuenta' => 'backoffice', 'email' => $data['email'] ?? null,
                    'password' => $password, 'estado' => true, 'must_change_password' => true,
                ]);
                $user->syncRoles([$data['role']]);
                $user->assertIdentity();
                $this->record('backoffice_created', $user, $actor, ['role' => $data['role']]);

                return ['user' => $user, 'temporary_password' => $password];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $usernameConflict = str_contains((string) ($exception->errorInfo[2] ?? ''), 'users_username_unique');
            throw new IdentityException($usernameConflict ? 'BACKOFFICE_USERNAME_TAKEN' : 'BACKOFFICE_EMAIL_TAKEN',
                $usernameConflict ? 'Este usuario ya está registrado.' : 'Este correo ya está registrado.', 422);
        }
    }

    public function update(User $target, array $data, User $actor): User
    {
        $this->authorize($actor, 'update', $target);
        $this->validate($data, false, $target);

        return DB::transaction(function () use ($target, $data, $actor) {
            $this->lockLifecycle();
            if (isset($actor)) {
                $this->authorize($actor->fresh(), 'update', $target);
            }
            $target = User::lockForUpdate()->findOrFail($target->id);
            $old = $target->only(['name', 'email']);
            $target->update($data);
            $this->record('backoffice_updated', $target, $actor, ['previous' => $old, 'new' => $target->only(['name', 'email'])]);

            return $target;
        });
    }

    public function transition(User $target, string $action, array $data, User $actor): array
    {
        $this->authorize($actor, 'update', $target);
        Validator::make($data, match ($action) {
            'deactivate' => ['motivo' => ['required', 'string', 'min:3', 'max:1000']],
            'role' => ['role' => ['required', Rule::in(['admin', 'secretario'])]],
            default => [],
        })->validate();
        if ($action === 'deactivate' && trim($data['motivo']) === '') {
            throw new IdentityException('DEACTIVATION_REASON_REQUIRED', 'Indique el motivo de desactivación.', 422);
        }

        return DB::transaction(function () use ($target, $action, $data, $actor) {
            $this->lockLifecycle();
            if (isset($actor)) {
                $this->authorize($actor->fresh(), 'update', $target);
            }
            $target = User::lockForUpdate()->findOrFail($target->id);
            $oldRole = $target->getRoleNames()->first();
            $removesAdmin = $target->estado && $oldRole === 'admin'
                && ($action === 'deactivate' || ($action === 'role' && $data['role'] !== 'admin'));
            if ($removesAdmin && User::where('tipo_cuenta', 'backoffice')->where('estado', true)->role('admin')->count() <= 1) {
                throw new IdentityException('LAST_ACTIVE_ADMIN', 'Debe conservar al menos un administrador activo.');
            }
            if ($target->id === $actor->id && $action === 'deactivate') {
                throw new IdentityException('CANNOT_DEACTIVATE_SELF', 'Otra cuenta administradora debe desactivar su acceso.');
            }
            if ($target->id === $actor->id && $action === 'role' && $data['role'] !== 'admin') {
                throw new IdentityException('CANNOT_CHANGE_OWN_ROLE', 'Otra cuenta administradora debe cambiar su rol.');
            }
            $metadata = [];
            $password = null;
            switch ($action) {
                case 'deactivate':
                    $target->forceFill(['estado' => false, 'desactivado_at' => now(),
                        'desactivado_por' => $actor->id, 'motivo_desactivacion' => trim($data['motivo'])])->save();
                    $metadata['motivo'] = trim($data['motivo']);
                    $event = 'backoffice_deactivated';
                    break;
                case 'reactivate':
                    // Always issue a fresh temporary credential; never revive an old temporary.
                    $target->estado = true;
                    $password = $this->temporaryPassword($target);
                    $event = 'backoffice_reactivated';
                    break;
                case 'reset':
                    $password = $this->temporaryPassword($target);
                    $event = 'backoffice_password_reset';
                    break;
                case 'role':
                    $target->syncRoles([$data['role']]);
                    $metadata = ['previous_role' => $oldRole, 'new_role' => $data['role']];
                    $event = 'backoffice_role_changed';
                    break;
                default:
                    throw new IdentityException('INVALID_ACCOUNT_ACTION', 'Acción de cuenta inválida.');
            }
            $target->revokeAuthentication();
            $this->record($event, $target, $actor, $metadata);

            return ['user' => $target->fresh(), 'temporary_password' => $password];
        });
    }

    public function provisionLegacy(User $target, string $username, string $role): array
    {
        $username = Str::lower(trim($username));
        $this->validate(['name' => $target->name, 'username' => $username, 'role' => $role], true);

        return DB::transaction(function () use ($target, $username, $role) {
            $this->lockLifecycle();
            $target = User::lockForUpdate()->findOrFail($target->id);
            if ($target->tipo_cuenta !== 'legacy') {
                throw new IdentityException('ACCOUNT_ALREADY_PROVISIONED', 'Solo se provisionan cuentas legacy; el username existente es inmutable.');
            }
            if (User::where('username', $username)->exists()) {
                throw new IdentityException('BACKOFFICE_USERNAME_TAKEN', 'Este usuario ya está registrado.');
            }
            $employeeId = $target->funcionario?->id;
            $target->funcionario?->update(['user_id' => null]);
            $target->forceFill(['username' => $username, 'documento' => null, 'tipo_cuenta' => 'backoffice']);
            $password = $this->temporaryPassword($target);
            $target->syncRoles([$role]);
            $target->revokeAuthentication();
            $target->assertIdentity();
            $this->record('backoffice_username_provisioned', $target, null, ['role' => $role, 'detached_employee_id' => $employeeId]);

            return ['user' => $target, 'temporary_password' => $password];
        });
    }

    private function validate(array $data, bool $creation, ?User $target = null): void
    {
        if (isset($data['role']) && ! in_array($data['role'], ['admin', 'secretario'], true)) {
            throw new IdentityException('INVALID_ACCOUNT_ROLE', 'Solo se permite Administrador o Secretaría.', 422);
        }
        if (array_diff(array_keys($data), $creation ? ['name', 'username', 'email', 'role'] : ['name', 'email'])) {
            throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'No se permiten datos laborales ni cambios de username.', 422);
        }
        if ($creation && in_array($data['username'], ['admin', 'administrador', 'secretaria', 'secretario'], true)) {
            throw new IdentityException('INDIVIDUAL_USERNAME_REQUIRED', 'Use un usuario individual, no una identidad compartida.', 422);
        }
        Validator::make($data, [
            'name' => [$creation ? 'required' : 'sometimes', 'string', 'min:2', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($target?->id)],
            ...($creation ? ['username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-z0-9._-]+$/', 'not_regex:/^[0-9]+$/'],
                'role' => ['required', Rule::in(['admin', 'secretario'])]] : []),
        ])->validate();
    }

    private function temporaryPassword(User $user): string
    {
        $password = Str::password(24);
        $user->forceFill(['password' => $password, 'must_change_password' => true, 'password_changed_at' => null])->save();

        return $password;
    }

    private function lockLifecycle(): void
    {
        // A constant advisory transaction lock also protects empty-table/bootstrap races.
        DB::select("SELECT pg_advisory_xact_lock(hashtext('backoffice_identity_lifecycle'))");
    }

    private function authorize(?User $actor, string $ability, ?User $target = null): void
    {
        if ($actor === null) {
            if (! app()->runningInConsole()) {
                abort(403);
            }

            return;
        }
        abort_unless($actor->estado && ! $actor->must_change_password, 403);
        Gate::forUser($actor)->authorize($ability, $target ?? User::class);
    }

    private function record(string $event, User $target, ?User $actor, array $metadata): void
    {
        $this->audit->execute(accion: $event, modelo: 'User', modeloId: $target->id,
            descripcion: 'Ciclo de vida de cuenta interna.', metadata: $metadata + ['target_user_id' => $target->id],
            actorId: $actor?->id);
    }
}
