<?php

namespace App\Providers;

use App\Models\SolicitudCertificacion;
use App\Models\PagoSoporte;
use App\Models\Certificado;
use App\Policies\PagoSoportePolicy;
use App\Policies\CertificadoPolicy;
use App\Policies\SolicitudCertificacionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
    }
}
