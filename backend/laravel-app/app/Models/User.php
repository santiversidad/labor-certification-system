<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
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
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'estado' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Laravel Auth usa este método para identificar al usuario.
     * Lo sobreescribimos para usar 'documento' en lugar de 'email'.
     */
    public function getAuthIdentifierName(): string
    {
        return 'documento';
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function funcionario(): HasOne
    {
        return $this->hasOne(Funcionario::class);
    }
}
