<?php

namespace App\Services;

use App\Actions\RegistrarAuditoriaAction;
use App\Exceptions\IdentityException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class MfaService
{
    public function __construct(private readonly Google2FA $totp, private readonly BrowserAuthentication $auth) {}

    public function setup(Request $request): array
    {
        $user = $this->auth->principal($request);
        abort_unless($user->isBackofficeAccount() && ! $user->must_change_password && ! $user->mfa_confirmed_at, 403);
        $encrypted = $request->session()->get('mfa_setup_secret');
        if (! $encrypted) {
            $encrypted = Crypt::encryptString($this->totp->generateSecretKey(32));
            $request->session()->put('mfa_setup_secret', $encrypted);
        }
        // Only explicit setup returns this value, never /me or a persisted QR image.
        return ['secret' => Crypt::decryptString($encrypted), 'issuer' => 'Certificaciones Villavicencio', 'account' => $user->username];
    }

    public function verify(Request $request, bool $enrollment = false): array
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        return DB::transaction(function () use ($request, $data, $enrollment) {
            $principal = $this->auth->principal($request);
            $user = User::lockForUpdate()->findOrFail($principal->id);
            // Resolve again under the row lock to detect concurrent reset/deactivation.
            $this->auth->principal($request);
            abort_unless($user->isBackofficeAccount() && ! $user->must_change_password, 403);
            $codes = [];
            if ($enrollment) {
                abort_if($user->mfa_confirmed_at || ! $request->session()->has('mfa_setup_secret'), 403);
                $secret = Crypt::decryptString($request->session()->get('mfa_setup_secret'));
                $step = $this->totp->verifyKeyNewer($secret, $data['code'], 0, 1);
                if ($step === false) {
                    $this->invalid($user);
                }
                $user->mfa_secret = $secret; // encrypted Eloquent cast
                $user->mfa_confirmed_at = now();
                $user->mfa_last_step = $step;
                $codes = $this->regenerate($user);
                $event = 'mfa_enrolled';
            } else {
                abort_unless($user->mfa_confirmed_at && $user->mfa_secret, 403);
                $this->consume($user, $data['code']);
                $event = 'mfa_success';
            }
            $user->save();
            $this->auth->complete($request, $user);
            SecurityEvents::record($event, ['user_id' => $user->id]);
            app(RegistrarAuditoriaAction::class)->execute(accion: $event, modelo: 'User', modeloId: $user->id,
                descripcion: 'Autenticación en dos pasos completada.', actorId: $user->id);

            return ['user' => $user, 'recovery_codes' => $codes];
        });
    }

    public function consume(User $user, string $code, bool $allowRecovery = true): void
    {
        if (preg_match('/^\d{6}$/D', $code)) {
            $step = $this->totp->verifyKeyNewer($user->mfa_secret, $code, (int) $user->mfa_last_step, 1);
            if ($step !== false) {
                $user->mfa_last_step = $step;
                return;
            }
        } elseif ($allowRecovery) {
            $hash = hash('sha256', strtolower(trim($code)));
            $hashes = $user->mfa_recovery_hashes ?? [];
            foreach ($hashes as $index => $stored) {
                if (hash_equals($stored, $hash)) {
                    unset($hashes[$index]);
                    $user->mfa_recovery_hashes = array_values($hashes);
                    return;
                }
            }
        }
        $this->invalid($user);
    }

    public function regenerate(User $user): array
    {
        $codes = array_map(fn () => implode('-', str_split(bin2hex(random_bytes(16)), 8)), range(1, 8));
        $user->mfa_recovery_hashes = array_map(fn ($code) => hash('sha256', $code), $codes);
        return $codes;
    }

    public function reset(User $target, User $actor): void
    {
        abort_unless($actor->estado && ! $actor->must_change_password && $actor->isBackofficeAccount() && $actor->hasRole('admin'), 403);
        Gate::forUser($actor)->authorize('update', $target);
        if ($target->id === $actor->id) {
            throw new IdentityException('MFA_SELF_RESET_FORBIDDEN', 'Otro administrador debe verificar su identidad y restablecer su MFA.', 409);
        }
        DB::transaction(function () use ($target, $actor) {
            DB::select("SELECT pg_advisory_xact_lock(hashtext('backoffice_identity_lifecycle'))");
            abort_unless($actor->fresh()->estado && ! $actor->fresh()->must_change_password, 403);
            Gate::forUser($actor->fresh())->authorize('update', $target);
            $target = User::lockForUpdate()->findOrFail($target->id);
            if ($target->hasRole('admin') && User::where('tipo_cuenta', 'backoffice')->where('estado', true)->role('admin')->count() <= 1) {
                throw new IdentityException('LAST_ACTIVE_ADMIN', 'Debe conservar al menos un administrador activo.', 409);
            }
            $target->forceFill(['mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_step' => null, 'mfa_recovery_hashes' => null])->save();
            $target->revokeAuthentication();
            SecurityEvents::record('mfa_reset', ['user_id' => $actor->id, 'target_user_id' => $target->id]);
            app(RegistrarAuditoriaAction::class)->execute(accion: 'mfa_reset', modelo: 'User', modeloId: $target->id,
                descripcion: 'Restablecimiento controlado de MFA por otro administrador.', actorId: $actor->id);
        });
    }

    private function invalid(User $user): never
    {
        SecurityEvents::record('mfa_failed', ['user_id' => $user->id]);
        throw ValidationException::withMessages(['code' => ['El código de autenticación no es válido o ya fue utilizado.']]);
    }
}
