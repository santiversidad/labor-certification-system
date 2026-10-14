<?php

namespace App\Models;

use App\Exceptions\IdentityException;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles {
        assignRole as private assignRoleUnchecked;
        syncRoles as private syncRolesUnchecked;
    }

    protected $fillable = [
        'name', 'username', 'tipo_cuenta', 'auth_version', 'last_login_at',
        'desactivado_at', 'desactivado_por', 'motivo_desactivacion',
        'documento',
        'password',
        'telefono',
        'estado',
        'must_change_password',
        'password_changed_at',
        'email',       // opcional: solo para notificaciones
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret', 'mfa_recovery_hashes', 'mfa_last_step',
    ];

    protected function casts(): array
    {
        return [
            'mfa_secret' => 'encrypted',
            'mfa_recovery_hashes' => 'array',
            'mfa_confirmed_at' => 'datetime',
            'mfa_last_step' => 'integer',
            'password' => 'hashed',
            'estado' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime', 'desactivado_at' => 'datetime', 'auth_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (! $user->exists && ! in_array($user->tipo_cuenta, ['funcionario', 'backoffice'], true)) {
                throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'Debe indicar el tipo de cuenta.', 422);
            }
            if ($user->exists && $user->getOriginal('tipo_cuenta') === 'backoffice' && $user->isDirty('username')) {
                throw new IdentityException('USERNAME_IMMUTABLE', 'El usuario institucional es inmutable.');
            }
            if ($user->tipo_cuenta === 'funcionario') {
                if (! $user->documento) {
                    throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'La cuenta funcionario requiere documento.');
                }
                $user->username = strtolower(trim($user->documento));
            } elseif ($user->tipo_cuenta === 'backoffice') {
                if ($user->documento !== null || ($user->exists && $user->funcionario()->exists())) {
                    throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'Backoffice no admite vínculo laboral.');
                }
                $user->username = strtolower(trim((string) $user->username));
                if (! preg_match('/^[a-z0-9._-]{3,64}$/', $user->username) || ctype_digit($user->username)) {
                    throw new IdentityException('INVALID_BACKOFFICE_USERNAME', 'Usuario institucional inválido.', 422);
                }
            }
        });
        static::deleting(fn () => throw new IdentityException('USER_DELETION_PROHIBITED', 'Desactive el acceso; preserve el actor histórico.'));
    }

    // Authentication sessions use the stable primary key, never a transferable username.
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function isFuncionarioAccount(): bool
    {
        return $this->tipo_cuenta === 'funcionario' && $this->funcionario()->exists()
            && $this->getRoleNames()->all() === ['funcionario'];
    }

    public function isBackofficeAccount(): bool
    {
        $roles = $this->getRoleNames()->all();

        return $this->tipo_cuenta === 'backoffice' && ! $this->funcionario()->exists()
            && count($roles) === 1 && in_array($roles[0], ['admin', 'secretario'], true);
    }

    public function assertIdentity(): void
    {
        if (! $this->isFuncionarioAccount() && ! $this->isBackofficeAccount()) {
            throw new IdentityException('ACCOUNT_CONTEXT_MISMATCH', 'La identidad requiere provisión o vínculo laboral válido.', 403);
        }
    }

    public function assignRole(...$roles)
    {
        $names = collect($roles)->flatten()->map(fn ($role) => $role instanceof \BackedEnum ? $role->value
            : ($role instanceof Role ? $role->name : $role))->all();
        $this->validateAccountRoles(array_unique([...$this->getRoleNames()->all(), ...$names]));

        return $this->assignRoleUnchecked(...$roles);
    }

    public function syncRoles(...$roles)
    {
        $names = collect($roles)->flatten()->map(fn ($role) => $role instanceof \BackedEnum ? $role->value
            : ($role instanceof Role ? $role->name : $role))->all();
        $this->validateAccountRoles($names);

        return $this->syncRolesUnchecked(...$roles);
    }

    private function validateAccountRoles(array $roles): void
    {
        // Empty sets are useful for revoking all permissions; such accounts fail authorization.
        if ($roles === []) {
            return;
        }
        $allowed = $this->tipo_cuenta === 'funcionario' ? ['funcionario']
            : ($this->tipo_cuenta === 'backoffice' ? ['admin', 'secretario'] : []);
        if (count($roles) !== 1 || ! in_array(reset($roles), $allowed, true)) {
            throw new IdentityException('INVALID_ACCOUNT_ROLE', 'El rol no corresponde al tipo de cuenta.', 422);
        }
    }

    public function revokeAuthentication(): void
    {
        $this->increment('auth_version');
        $this->tokens()->delete();
        $this->forceFill(['remember_token' => null])->save();
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function funcionario(): HasOne
    {
        return $this->hasOne(Funcionario::class);
    }
}
