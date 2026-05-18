<?php

use Illuminate\Support\Facades\Route;
use App\Modules\CashAdvances\Http\Controllers\CashAdvanceController;

Route::middleware(['auth.external'])->group(function () {
    Route::get('/', [CashAdvanceController::class, 'index']);
    Route::post('/', [CashAdvanceController::class, 'store']);
    Route::get('/{id}', [CashAdvanceController::class, 'show']);
    Route::post('/{id}/disburse', [CashAdvanceController::class, 'disburse']);
});
