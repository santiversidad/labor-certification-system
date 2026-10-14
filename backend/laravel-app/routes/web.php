<?php

use Illuminate\Support\Facades\Route;

Route::post('/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout'])->middleware(['auth.principal', 'identity.current']);

Route::get('/', function () {
    return view('welcome');
});
