<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Expenses\Http\Controllers\ExpenseController;

Route::middleware(['auth.external'])->group(function () {
    Route::get('/', [ExpenseController::class, 'index']);
    Route::post('/', [ExpenseController::class, 'store']);
    Route::get('/{id}', [ExpenseController::class, 'show']);
    Route::put('/{id}', [ExpenseController::class, 'update']);
    Route::delete('/{id}', [ExpenseController::class, 'destroy']);
});
