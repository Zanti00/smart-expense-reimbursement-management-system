<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Shared\Http\Controllers\AuthController;
use App\Modules\Shared\Http\Controllers\CryptoController;

Route::prefix('api')->group(function () {
    // Public: server RSA public key for client-side payload encryption (SDD §5).
    Route::get('/crypto/key', [CryptoController::class, 'publicKey']);

    Route::middleware('auth.external')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword']);
    });
});
