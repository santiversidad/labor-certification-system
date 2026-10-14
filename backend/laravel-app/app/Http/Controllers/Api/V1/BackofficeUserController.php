<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BackofficeIdentityService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackofficeUserController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly BackofficeIdentityService $identities) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', 'boolean'], 'role' => ['nullable', Rule::in(['admin', 'secretario'])],
            'page' => ['nullable', 'integer', 'min:1']]);
        $users = User::where('tipo_cuenta', 'backoffice')->with('roles')
            ->when(isset($data['estado']), fn ($q) => $q->where('estado', $data['estado']))
            ->when($data['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('name', 'ilike', '%'.$term.'%')->orWhere('username', 'ilike', '%'.$term.'%')))
            ->orderBy('name')->orderBy('id')->paginate(15);

        return $this->successResponse(UserResource::collection($users)->response()->getData(true));
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        return $this->successResponse(new UserResource($user));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->identities->create($request->all(), $request->user());

        return $this->secretResponse($result, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        return $this->successResponse(new UserResource($this->identities->update($user, $request->all(), $request->user())));
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        return $this->secretResponse($this->identities->transition($user, 'deactivate', $request->all(), $request->user()));
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        return $this->secretResponse($this->identities->transition($user, 'reactivate', [], $request->user()));
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        return $this->secretResponse($this->identities->transition($user, 'reset', [], $request->user()));
    }

    public function changeRole(Request $request, User $user): JsonResponse
    {
        return $this->secretResponse($this->identities->transition($user, 'role', $request->all(), $request->user()));
    }

    private function secretResponse(array $result, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'user' => new UserResource($result['user']), 'temporary_password' => $result['temporary_password'],
        ]], $status)->header('Cache-Control', 'private, no-store');
    }
}
