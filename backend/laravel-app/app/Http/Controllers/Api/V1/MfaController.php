<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BrowserAuthentication;
use App\Services\MfaService;
use App\Services\SecurityEvents;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    use ApiResponse;

    public function setup(Request $request, MfaService $mfa)
    {
        return $this->successResponse($mfa->setup($request));
    }

    public function confirm(Request $request, MfaService $mfa)
    {
        return $this->result($mfa->verify($request, true));
    }

    public function verify(Request $request, MfaService $mfa)
    {
        return $this->result($mfa->verify($request));
    }

    public function reauthenticate(Request $request, MfaService $mfa, BrowserAuthentication $auth)
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:128'], 'code' => ['required', 'string', 'max:64']]);
        DB::transaction(function () use ($request, $data, $mfa, $auth) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            $auth->principal($request);
            abort_unless($user->isBackofficeAccount() && $user->mfa_confirmed_at, 403);
            if (! Hash::check($data['password'], $user->password)) {
                SecurityEvents::record('reauth_failed', ['user_id' => $user->id]);
                throw ValidationException::withMessages(['password' => ['No fue posible verificar la autenticación.']]);
            }
            $mfa->consume($user, $data['code']);
            $user->save();
            $request->session()->regenerate();
            $request->session()->put('step_up_user', $user->id);
            $request->session()->put('step_up_at', time());
        });
        return $this->successResponse(null, 'Autenticación confirmada por cinco minutos.');
    }

    public function recovery(Request $request, MfaService $mfa)
    {
        $codes = DB::transaction(function () use ($request, $mfa) {
            $user = User::lockForUpdate()->findOrFail($request->user()->id);
            app(BrowserAuthentication::class)->principal($request);
            abort_unless($user->isBackofficeAccount() && $user->mfa_confirmed_at, 403);
            $codes = $mfa->regenerate($user);
            $user->save();
            SecurityEvents::record('mfa_recovery_regenerated', ['user_id' => $user->id]);
            return $codes;
        });
        return $this->successResponse(['recovery_codes' => $codes]);
    }

    public function reset(Request $request, User $user, MfaService $mfa)
    {
        $mfa->reset($user, $request->user());
        return $this->successResponse(null, 'MFA restablecido. El usuario deberá configurarlo nuevamente.');
    }

    private function result(array $result)
    {
        return $this->successResponse(['user' => new UserResource($result['user']), 'recovery_codes' => $result['recovery_codes']]);
    }
}
