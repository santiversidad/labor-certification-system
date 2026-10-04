<?php

namespace App\Providers;

use App\Models\Certificado;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
use App\Models\User;
use App\Policies\BackofficeUserPolicy;
use App\Policies\CertificadoPolicy;
use App\Policies\PagoSoportePolicy;
use App\Policies\SolicitudCertificacionPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(User::class, BackofficeUserPolicy::class);
        // Policies del sistema
        Gate::policy(SolicitudCertificacion::class, SolicitudCertificacionPolicy::class);
        Gate::policy(PagoSoporte::class, PagoSoportePolicy::class);
        Gate::policy(Certificado::class, CertificadoPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $documento = Str::lower(trim((string) $request->input('username', $request->input('cedula', ''))));

            $response = function (Request $request, array $headers) {
                \App\Services\SecurityEvents::record('login_rate_limited', ['ip_hash' => hash('sha256', (string) $request->ip())]);
                return response()->json(['success' => false, 'message' => 'Demasiados intentos de inicio de sesión. Intente nuevamente más tarde.', 'code' => 'LOGIN_RATE_LIMIT'], 429, $headers);
            };
            return [Limit::perMinute(30)->by('ip:'.$request->ip())->response($response),
                Limit::perMinute(5)->by('pair:'.hash('sha256', $documento).'|'.$request->ip())->response($response),
                Limit::perMinute(10)->by('identity:'.hash('sha256', $documento))->response($response)];
        });
        foreach (['mfa' => 5, 'mfa-setup' => 5, 'password-change' => 5, 'reauth' => 5, 'security-action' => 5,
            'public-validation' => 30, 'certificate-generation' => 5, 'payment-upload' => 10,
            'payment-review' => 30, 'provisioning' => 60, 'authenticated' => 120] as $name => $limit) {
            RateLimiter::for($name, fn (Request $request) => [
                Limit::perMinute($limit)->by($name.':actor:'.($request->user()?->id ?? ($request->hasSession() ? $request->session()->get('pending_auth.user_id') : null) ?? $request->ip())),
                Limit::perMinute($limit * 3)->by($name.':ip:'.$request->ip()),
            ]);
        }
    }
}
