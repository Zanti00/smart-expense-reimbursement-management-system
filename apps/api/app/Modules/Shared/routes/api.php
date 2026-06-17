<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Shared\Http\Controllers\AuthController;

Route::prefix('api')->middleware('auth.external')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword']);
});
