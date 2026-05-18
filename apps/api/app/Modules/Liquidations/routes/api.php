<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Liquidations\Http\Controllers\LiquidationController;

Route::middleware(['auth.external'])->group(function () {
    Route::get('/', [LiquidationController::class, 'index']);
    Route::post('/', [LiquidationController::class, 'store']);
    Route::get('/{id}', [LiquidationController::class, 'show']);
    Route::post('/{id}/audit', [LiquidationController::class, 'audit']);
});
