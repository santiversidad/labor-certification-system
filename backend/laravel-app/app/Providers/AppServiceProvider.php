<?php

namespace App\Providers;

use App\Models\Certificado;
use App\Models\PagoSoporte;
use App\Models\SolicitudCertificacion;
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
        // Policies del sistema
        Gate::policy(SolicitudCertificacion::class, SolicitudCertificacionPolicy::class);
        Gate::policy(PagoSoporte::class, PagoSoportePolicy::class);
        Gate::policy(Certificado::class, CertificadoPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $documento = Str::lower((string) $request->input('cedula', ''));

            return Limit::perMinute(5)
                ->by($documento.'|'.$request->ip())
                ->response(fn () => response()->json([
                    'success' => false,
                    'message' => 'Demasiados intentos de inicio de sesión. Intente nuevamente en un minuto.',
                    'code' => 'LOGIN_RATE_LIMIT',
                ], 429));
        });
    }
}
