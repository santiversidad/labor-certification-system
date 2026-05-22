<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CargoController;
use App\Http\Controllers\Api\V1\FuncionarioController;
use App\Http\Controllers\Api\V1\RangoSalarialController;
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

    });

});
