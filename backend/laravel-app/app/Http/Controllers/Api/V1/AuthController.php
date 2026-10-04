<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Enums\EstadoFuncionarioEnum;
use App\Exceptions\IdentityException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly RegistrarAuditoriaAction $registrarAuditoria,
    ) {}

    /**
     * POST /api/v1/auth/login
     *
     * Autentica un usuario con su número de cédula y contraseña.
     * Retorna un token Sanctum junto con la información del usuario y sus roles.
     */
    public function login(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $field = $request->has('username') ? 'username' : 'cedula';
            $request->validate([
                $field => ['required', 'string', 'max:64'],
                'password' => ['required', 'string'],
            ]);

            $username = strtolower(trim((string) $request->input($field)));
            $user = User::where('username', $username)->lockForUpdate()->first();

            // Verificar existencia y contraseña antes de revelar el estado
            if (! $user || ! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    $field => ['El usuario o contraseña son incorrectos.'],
                ]);
            }

            if (! $user->estado) {
                return $this->errorResponse(
                    'Su cuenta está inactiva. Contacte al administrador.',
                    null,
                    403,
                    'BACKOFFICE_USER_INACTIVE'
                );
            }

            $user->assertIdentity();

            if ($user->isFuncionarioAccount() && $user->funcionario?->estado !== EstadoFuncionarioEnum::Activo) {
                return $this->errorResponse(
                    'Su vinculación de funcionario no está activa. Contacte al administrador.',
                    null,
                    403
                );
            }

            // Revocar tokens previos para evitar sesiones simultáneas huérfanas
            $user->tokens()->delete();

            $token = $user->createToken('api-token')->plainTextToken;
            $user->forceFill(['last_login_at' => now()])->save();
            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->put('auth_version', $user->auth_version);
            }

            $this->registrarAuditoria->execute(
                accion: 'login',
                modelo: 'User',
                modeloId: $user->id,
                descripcion: "Inicio de sesión: usuario {$user->username}",
                actorId: $user->id,
            );

            return $this->successResponse([
                'token' => $token,
                'user' => new UserResource($user),
            ], 'Sesión iniciada correctamente.');
        });
    }

    /**
     * POST /api/v1/auth/logout
     * Revoca el token actual del usuario autenticado.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->registrarAuditoria->execute(
            accion: 'logout',
            modelo: 'User',
            modeloId: $user->id,
            descripcion: "Cierre de sesión: usuario {$user->username}",
        );

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        // currentAccessToken() puede ser null en entorno de tests con actingAs()
        if ($user->currentAccessToken() instanceof PersonalAccessToken) {
            $user->currentAccessToken()->delete();
        } else {
            Auth::guard('web')->logout();
        }

        return $this->successResponse(null, 'Sesión cerrada correctamente.');
    }

    /**
     * GET /api/v1/auth/me
     * Retorna el usuario autenticado con sus roles y, si existe, su ficha de funcionario.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()->load('funcionario')),
            'Usuario autenticado.'
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            // Revalidate after acquiring the lock: a lifecycle action may have just revoked access.
            $originalToken = $request->user()->currentAccessToken();
            if (! $user->estado || ($originalToken instanceof PersonalAccessToken && ! $originalToken->fresh())) {
                throw new IdentityException('SESSION_REVOKED', 'Inicie sesión nuevamente.', 401);
            }
            if ($originalToken instanceof TransientToken
                && (! $request->hasSession() || $request->session()->get('auth_version') !== $user->auth_version)) {
                throw new IdentityException('SESSION_REVOKED', 'Inicie sesión nuevamente.', 401);
            }
            $user->assertIdentity();
            $user->withAccessToken($originalToken);

            if (! Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['La contraseña actual no es correcta.'],
                ]);
            }

            $wasForced = $user->must_change_password;
            $user->forceFill([
                'password' => $request->validated('password'),
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();

            $user->increment('auth_version');
            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->put('auth_version', $user->auth_version);
            }
            // Keep this bearer session coherent while invalidating any other issued tokens.
            $currentToken = $user->currentAccessToken();
            if ($currentToken instanceof PersonalAccessToken) {
                $currentToken->forceFill(['created_at' => now()])->save();
            }
            $user->tokens()->when($currentToken instanceof PersonalAccessToken,
                fn ($query) => $query->where('id', '<>', $currentToken->id))->delete();

            $this->registrarAuditoria->execute(
                accion: 'forced_password_changed',
                modelo: 'User',
                modeloId: $user->id,
                descripcion: 'El usuario completó el cambio de su contraseña temporal.',
                metadata: ['must_change_password' => ['anterior' => $wasForced, 'nuevo' => false]],
            );

            return $this->successResponse(
                new UserResource($user->fresh()->load('funcionario')),
                'Contraseña actualizada correctamente.'
            );
        });
    }
}
