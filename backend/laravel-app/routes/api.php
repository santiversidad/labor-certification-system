<?php

use App\Http\Controllers\Api\V1\ActuacionAdministrativaController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackofficeUserController;
use App\Http\Controllers\Api\V1\CargoController;
use App\Http\Controllers\Api\V1\CertificadoController;
use App\Http\Controllers\Api\V1\DisponibilidadCertificacionController;
use App\Http\Controllers\Api\V1\FuncionarioController;
use App\Http\Controllers\Api\V1\ManualFuncionController;
use App\Http\Controllers\Api\V1\MiCertificacionController;
use App\Http\Controllers\Api\V1\MfaController;
use App\Http\Controllers\Api\V1\PagoSolicitudController;
use App\Http\Controllers\Api\V1\PagoSoporteController;
use App\Http\Controllers\Api\V1\ParametroCertificacionController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\SolicitudCertificacionController;
use App\Http\Controllers\Api\V1\ValidacionPublicaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::get('validar-certificado/{token}', [ValidacionPublicaController::class, 'show'])->middleware('throttle:public-validation')->name('validar-certificado.show');
    Route::get('certificados/validar/{token}', [ValidacionPublicaController::class, 'show'])->middleware('throttle:public-validation')->name('certificados.validar.show');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
        Route::middleware(['auth.principal', 'identity.current'])->group(function () {
            // Estas son las únicas operaciones permitidas mientras must_change_password=true.
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::put('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:password-change')->name('change-password');
            Route::post('mfa/setup', [MfaController::class, 'setup'])->middleware(['password.changed', 'throttle:mfa-setup']);
            Route::post('mfa/confirm', [MfaController::class, 'confirm'])->middleware(['password.changed', 'throttle:mfa']);
            Route::post('mfa/verify', [MfaController::class, 'verify'])->middleware(['password.changed', 'throttle:mfa']);
        });
    });

    Route::middleware(['auth:sanctum', 'identity.current', 'password.changed', 'mfa', 'throttle:authenticated'])->group(function () {
        Route::post('auth/reauthenticate', [MfaController::class, 'reauthenticate'])->middleware('throttle:reauth');
        Route::post('auth/mfa/recovery-codes', [MfaController::class, 'recovery'])->middleware(['auth.recent', 'throttle:security-action']);
        Route::get('mi-certificacion/disponibilidad', DisponibilidadCertificacionController::class)->middleware('context.funcionario')->name('mi-certificacion.disponibilidad');
        Route::get('mi-certificacion/descargar/{token}', [MiCertificacionController::class, 'descargar'])->middleware('context.funcionario')->name('mi-certificacion.descargar');
        Route::get('solicitudes/{solicitud}/certificado', [MiCertificacionController::class, 'descargarSolicitud'])->middleware('context.funcionario')->name('mi-certificacion.solicitud.descargar');
        Route::post('solicitudes/{solicitud}/pago/comprobantes', [PagoSolicitudController::class, 'cargar'])->middleware(['context.funcionario', 'throttle:payment-upload'])->name('solicitudes.pago.cargar');
        Route::get('solicitudes/{solicitud}/pago/comprobantes/{comprobante}', [PagoSolicitudController::class, 'archivo'])->name('solicitudes.pago.archivo');
        Route::prefix('admin/usuarios-internos')->middleware('throttle:provisioning')->group(function () {
            $controller = BackofficeUserController::class;
            Route::get('/', [$controller, 'index']);
            Route::post('/', [$controller, 'store']);
            Route::get('/{user}', [$controller, 'show']);
            Route::patch('/{user}', [$controller, 'update']);
            Route::post('/{user}/desactivar', [$controller, 'deactivate'])->middleware('auth.recent');
            Route::post('/{user}/reactivar', [$controller, 'reactivate']);
            Route::post('/{user}/reset-password', [$controller, 'resetPassword'])->middleware(['auth.recent', 'throttle:security-action']);
            Route::post('/{user}/cambiar-rol', [$controller, 'changeRole'])->middleware('auth.recent');
            Route::post('/{user}/reset-mfa', [MfaController::class, 'reset'])->middleware(['auth.recent', 'throttle:security-action']);
        });
        Route::prefix('secretaria/pagos')->name('secretaria.pagos.')->group(function () {
            Route::get('/', [PagoSolicitudController::class, 'index'])->name('index');
            Route::get('/{solicitud}', [PagoSolicitudController::class, 'show'])->name('show');
            Route::post('/{solicitud}/confirmar', [PagoSolicitudController::class, 'confirmar'])->middleware('throttle:payment-review')->name('confirmar');
            Route::post('/{solicitud}/rechazar', [PagoSolicitudController::class, 'rechazar'])->middleware('throttle:payment-review')->name('rechazar');
        });

        Route::prefix('configuracion/certificaciones')->name('configuracion.certificaciones.')->group(function () {
            Route::get('/', [ParametroCertificacionController::class, 'show'])->name('show');
            Route::patch('/', [ParametroCertificacionController::class, 'update'])->middleware('auth.recent')->name('update');
        });

        Route::prefix('manual-funciones')->name('manual-funciones.')->group(function () {
            Route::get('/dependencias', [\App\Http\Controllers\Api\V1\DependenciaManualController::class, 'index']);
            Route::get('/dependencias/cargos', [\App\Http\Controllers\Api\V1\DependenciaManualController::class, 'cargos']);
            Route::get('/dependencias/resolver', [\App\Http\Controllers\Api\V1\DependenciaManualController::class, 'resolver']);
            Route::get('/fichas', [ManualFuncionController::class, 'fichas'])->name('fichas');
            Route::get('/estado', [ManualFuncionController::class, 'estado'])->name('estado');
            Route::get('/diff/{from}/{to}', [ManualFuncionController::class, 'diff'])->name('diff');
            Route::post('/planificar/{from}/{to}', [ManualFuncionController::class, 'planificar'])->name('planificar');
            Route::post('/versiones/{version}/importar', [ManualFuncionController::class, 'importar'])->name('versiones.importar');
            Route::post('/actualizaciones-asignaciones/{actualizacion}/resolver', [ManualFuncionController::class, 'resolverAsignacion'])->name('actualizaciones.resolver');
            Route::get('/', [ManualFuncionController::class, 'index'])->name('index');
            Route::post('/', [ManualFuncionController::class, 'store'])->name('store');
            Route::post('/{manual}/versiones', [ManualFuncionController::class, 'storeVersion'])->name('versiones.store');
            Route::put('/versiones/{version}', [ManualFuncionController::class, 'updateVersion'])->name('versiones.update');
            Route::put('/versiones/{version}/cargos/{cargo}', [ManualFuncionController::class, 'upsertCargo'])->name('versiones.cargos.upsert');
            Route::post('/versiones/{version}/publicar', [ManualFuncionController::class, 'publicar'])->name('versiones.publicar');
        });

        Route::prefix('cargos')->name('cargos.')->group(function () {
            Route::get('/', [CargoController::class, 'index'])->name('index');
            Route::post('/', [CargoController::class, 'store'])->name('store');
            Route::get('/{cargo}', [CargoController::class, 'show'])->name('show');
            Route::put('/{cargo}', [CargoController::class, 'update'])->name('update');
        });

        Route::prefix('funcionarios')->name('funcionarios.')->group(function () {
            Route::get('/', [FuncionarioController::class, 'index'])->name('index');
            Route::post('/', [FuncionarioController::class, 'store'])->name('store');
            Route::get('/{funcionario}', [FuncionarioController::class, 'show'])->name('show');
            Route::put('/{funcionario}', [FuncionarioController::class, 'update'])->name('update');
            Route::post('/{funcionario}/restablecer-acceso', [FuncionarioController::class, 'resetAccess'])->name('reset-access');
            Route::delete('/{funcionario}', [FuncionarioController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('solicitudes')->name('solicitudes.')->group(function () {
            Route::get('/', [SolicitudCertificacionController::class, 'index'])->name('index');
            Route::post('/', [SolicitudCertificacionController::class, 'store'])->middleware(['context.funcionario', 'throttle:certificate-generation'])->name('store');
            Route::get('/{solicitud}', [SolicitudCertificacionController::class, 'show'])->name('show');

            // Compatibilidad de rutas: el flujo manual responde 410 y no altera estados.
            Route::patch('/{solicitud}/estado', [SolicitudCertificacionController::class, 'legacyManualFlow'])->name('cambiarEstado');
            Route::patch('/{solicitud}/aprobar', [SolicitudCertificacionController::class, 'legacyManualFlow'])->name('aprobar');
            Route::patch('/{solicitud}/rechazar', [SolicitudCertificacionController::class, 'legacyManualFlow'])->name('rechazar');
            Route::patch('/{solicitud}/marcar-pago', [SolicitudCertificacionController::class, 'legacyManualFlow'])->name('marcarPago');
            Route::post('/{solicitud}/soporte-pago', [PagoSoporteController::class, 'cargar'])->name('soporte-pago.cargar');
            Route::post('/{solicitud}/generar-certificado', [SolicitudCertificacionController::class, 'legacyManualFlow'])->name('generar-certificado');
        });

        // Consulta histórica de soportes permanece solo para registros legados; no recibe nuevas cargas.
        Route::prefix('pagos')->name('pagos.')->group(function () {
            Route::get('/', [PagoSoporteController::class, 'index'])->name('index');
            Route::get('/{pago}', [PagoSoporteController::class, 'show'])->name('show');
            Route::get('/{pago}/archivo', [PagoSoporteController::class, 'descargar'])->name('archivo');
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
