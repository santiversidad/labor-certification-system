<?php

namespace App\Providers;

use App\Models\SolicitudCertificacion;
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
    }
}
