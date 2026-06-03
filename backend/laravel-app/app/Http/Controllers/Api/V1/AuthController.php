<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegistrarAuditoriaAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
        $request->validate([
            'cedula'   => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('documento', $request->cedula)->first();

        // Verificar existencia y contraseña antes de revelar el estado
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'cedula' => ['La cédula o contraseña son incorrectas.'],
            ]);
        }

        if (! $user->estado) {
            return $this->errorResponse(
                'Su cuenta está inactiva. Contacte al administrador.',
                null,
                403
            );
        }

        // Revocar tokens previos para evitar sesiones simultáneas huérfanas
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        $this->registrarAuditoria->execute(
            accion: 'login',
            modelo: 'User',
            modeloId: $user->id,
            descripcion: "Inicio de sesión: cédula {$user->documento}",
        );

        return $this->successResponse([
            'token' => $token,
            'user'  => new UserResource($user),
        ], 'Sesión iniciada correctamente.');
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
            descripcion: "Cierre de sesión: cédula {$user->documento}",
        );

        // currentAccessToken() puede ser null en entorno de tests con actingAs()
        $user->currentAccessToken()?->delete();

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
}
