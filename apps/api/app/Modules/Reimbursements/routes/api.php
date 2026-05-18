<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reimbursements\Http\Controllers\ReimbursementController;

Route::middleware(['auth.external'])->group(function () {
    Route::get('/', [ReimbursementController::class, 'index']);
    Route::post('/', [ReimbursementController::class, 'store']);
    Route::get('/{id}', [ReimbursementController::class, 'show']);
    Route::post('/{id}/approve', [ReimbursementController::class, 'approve']);
    Route::post('/{id}/reject', [ReimbursementController::class, 'reject']);
});
