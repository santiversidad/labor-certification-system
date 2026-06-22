<?php

use App\Http\Controllers\Api\V1\ActuacionAdministrativaController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CargoController;
use App\Http\Controllers\Api\V1\CertificadoController;
use App\Http\Controllers\Api\V1\FuncionarioController;
use App\Http\Controllers\Api\V1\PagoSoporteController;
use App\Http\Controllers\Api\V1\RangoSalarialController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\SolicitudCertificacionController;
use App\Http\Controllers\Api\V1\ValidacionPublicaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::get('validar-certificado/{token}', [ValidacionPublicaController::class, 'show'])
        ->name('validar-certificado.show');
    Route::get('certificados/validar/{token}', [ValidacionPublicaController::class, 'show'])
        ->name('certificados.validar.show');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('cargos')->name('cargos.')->group(function () {
            Route::get('/', [CargoController::class, 'index'])->name('index');
            Route::post('/', [CargoController::class, 'store'])->name('store');
            Route::get('/{cargo}', [CargoController::class, 'show'])->name('show');
            Route::put('/{cargo}', [CargoController::class, 'update'])->name('update');
        });

        Route::prefix('rangos-salariales')->name('rangos-salariales.')->group(function () {
            Route::get('/consultar', [RangoSalarialController::class, 'consultar'])->name('consultar');
            Route::get('/', [RangoSalarialController::class, 'index'])->name('index');
            Route::post('/', [RangoSalarialController::class, 'store'])->name('store');
            Route::get('/{rango_salarial}', [RangoSalarialController::class, 'show'])->name('show');
            Route::put('/{rango_salarial}', [RangoSalarialController::class, 'update'])->name('update');
        });

        Route::prefix('funcionarios')->name('funcionarios.')->group(function () {
            Route::get('/', [FuncionarioController::class, 'index'])->name('index');
            Route::post('/', [FuncionarioController::class, 'store'])->name('store');
            Route::get('/{funcionario}', [FuncionarioController::class, 'show'])->name('show');
            Route::put('/{funcionario}', [FuncionarioController::class, 'update'])->name('update');
            Route::delete('/{funcionario}', [FuncionarioController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('solicitudes')->name('solicitudes.')->group(function () {
            Route::get('/', [SolicitudCertificacionController::class, 'index'])->name('index');
            Route::post('/', [SolicitudCertificacionController::class, 'store'])->name('store');
            Route::get('/{solicitud}', [SolicitudCertificacionController::class, 'show'])->name('show');
            Route::patch('/{solicitud}/estado', [SolicitudCertificacionController::class, 'cambiarEstado'])->name('cambiarEstado');
            Route::patch('/{solicitud}/aprobar', [SolicitudCertificacionController::class, 'aprobar'])->name('aprobar');
            Route::patch('/{solicitud}/rechazar', [SolicitudCertificacionController::class, 'rechazar'])->name('rechazar');
            Route::patch('/{solicitud}/marcar-pago', [SolicitudCertificacionController::class, 'marcarPago'])->name('marcarPago');
            Route::post('/{solicitud}/soporte-pago', [PagoSoporteController::class, 'cargar'])->name('soporte-pago.cargar');
            Route::post('/{solicitud}/generar-certificado', [CertificadoController::class, 'generar'])->name('generar-certificado');
        });

        Route::prefix('pagos')->name('pagos.')->group(function () {
            Route::get('/', [PagoSoporteController::class, 'index'])->name('index');
            Route::get('/{pago}', [PagoSoporteController::class, 'show'])->name('show');
            Route::patch('/{pago}/validar', [PagoSoporteController::class, 'validar'])->name('validar');
            Route::patch('/{pago}/rechazar', [PagoSoporteController::class, 'rechazar'])->name('rechazar');
        });

        Route::prefix('certificados')->name('certificados.')->group(function () {
            Route::get('/', [CertificadoController::class, 'index'])->name('index');
            Route::get('/{certificado}/descargar', [CertificadoController::class, 'descargar'])->name('descargar');
            Route::get('/{certificado}', [CertificadoController::class, 'show'])->name('show');
            Route::patch('/{certificado}/anular', [CertificadoController::class, 'anular'])->name('anular');
        });

        Route::get('auditoria', [AuditLogController::class, 'index'])->name('auditoria.index');
        Route::get('reportes', [ReporteController::class, 'resumen'])->name('reportes.index');
        Route::get('reportes/resumen', [ReporteController::class, 'resumen'])->name('reportes.resumen');

        Route::prefix('actuaciones-administrativas')->name('actuaciones.')->group(function () {
            Route::get('/', [ActuacionAdministrativaController::class, 'index'])->name('index');
            Route::post('/', [ActuacionAdministrativaController::class, 'store'])->name('store');
            Route::get('/{actuacion}', [ActuacionAdministrativaController::class, 'show'])->name('show');
            Route::put('/{actuacion}', [ActuacionAdministrativaController::class, 'update'])->name('update');
        });
    });
});
