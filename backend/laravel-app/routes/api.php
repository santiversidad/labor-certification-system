<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CargoController;
use App\Http\Controllers\Api\V1\FuncionarioController;
use App\Http\Controllers\Api\V1\RangoSalarialController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\CertificadoController;
use App\Http\Controllers\Api\V1\PagoSoporteController;
use App\Http\Controllers\Api\V1\SolicitudCertificacionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistema de Certificaciones Laborales (SCL)
| Alcaldía de Villavicencio, Meta
|--------------------------------------------------------------------------
| Todas las rutas privadas requieren autenticación via Laravel Sanctum.
| Prefijo base: /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('v1.')->group(function () {

    // ─── Autenticación ───────────────────────────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me',      [AuthController::class, 'me'])->name('me');
        });
    });

    // ─── Rutas protegidas ────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Cargos
        Route::prefix('cargos')->name('cargos.')->group(function () {
            Route::get('/',        [CargoController::class, 'index'])->name('index');
            Route::post('/',       [CargoController::class, 'store'])->name('store');
            Route::get('/{cargo}', [CargoController::class, 'show'])->name('show');
            Route::put('/{cargo}', [CargoController::class, 'update'])->name('update');
        });

        // Rangos salariales ← /consultar definido ANTES del wildcard
        Route::prefix('rangos-salariales')->name('rangos-salariales.')->group(function () {
            Route::get('/consultar',        [RangoSalarialController::class, 'consultar'])->name('consultar');
            Route::get('/',                 [RangoSalarialController::class, 'index'])->name('index');
            Route::post('/',                [RangoSalarialController::class, 'store'])->name('store');
            Route::get('/{rango_salarial}', [RangoSalarialController::class, 'show'])->name('show');
            Route::put('/{rango_salarial}', [RangoSalarialController::class, 'update'])->name('update');
        });

        // Funcionarios
        Route::prefix('funcionarios')->name('funcionarios.')->group(function () {
            Route::get('/',                 [FuncionarioController::class, 'index'])->name('index');
            Route::post('/',                [FuncionarioController::class, 'store'])->name('store');
            Route::get('/{funcionario}',    [FuncionarioController::class, 'show'])->name('show');
            Route::put('/{funcionario}',    [FuncionarioController::class, 'update'])->name('update');
            Route::delete('/{funcionario}', [FuncionarioController::class, 'destroy'])->name('destroy');
        });

        // Solicitudes de certificación
        Route::prefix('solicitudes')->name('solicitudes.')->group(function () {
            Route::get('/',                                 [SolicitudCertificacionController::class, 'index'])->name('index');
            Route::post('/',                                [SolicitudCertificacionController::class, 'store'])->name('store');
            Route::get('/{solicitud}',                      [SolicitudCertificacionController::class, 'show'])->name('show');

            // Endpoint genérico de transición de estado
            Route::patch('/{solicitud}/estado',             [SolicitudCertificacionController::class, 'cambiarEstado'])->name('cambiarEstado');

            // Alias de acciones específicas (secretario / admin)
            Route::patch('/{solicitud}/aprobar',            [SolicitudCertificacionController::class, 'aprobar'])->name('aprobar');
            Route::patch('/{solicitud}/rechazar',           [SolicitudCertificacionController::class, 'rechazar'])->name('rechazar');
            Route::patch('/{solicitud}/marcar-pago',        [SolicitudCertificacionController::class, 'marcarPago'])->name('marcarPago');

            // Carga de soporte (funcionario titular)
            Route::post('/{solicitud}/soporte-pago',        [PagoSoporteController::class, 'cargar'])->name('soporte-pago.cargar');

            // Generación de certificado — se completa en Fase 3
            Route::post('/{solicitud}/generar-certificado', [CertificadoController::class, 'generar'])->name('generar-certificado');
        });

        // Certificados
        Route::prefix('certificados')->name('certificados.')->group(function () {
            Route::get('/',                    [CertificadoController::class, 'index'])->name('index');
            Route::get('/{certificado}',       [CertificadoController::class, 'show'])->name('show');
        });

        // Auditoría (solo admin)
        Route::get('auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');

        // Pagos y soportes (validación por secretario/admin)
        Route::prefix('pagos')->name('pagos.')->group(function () {
            Route::get('/',                  [PagoSoporteController::class, 'index'])->name('index');
            Route::get('/{pago}',            [PagoSoporteController::class, 'show'])->name('show');
            Route::patch('/{pago}/validar',  [PagoSoporteController::class, 'validar'])->name('validar');
            Route::patch('/{pago}/rechazar', [PagoSoporteController::class, 'rechazar'])->name('rechazar');
        });

    });

});
