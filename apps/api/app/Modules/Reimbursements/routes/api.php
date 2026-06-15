<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reimbursements\Http\Controllers\ReimbursementController;
use App\Modules\Reimbursements\Http\Controllers\ReceiptController;
use App\Modules\Reimbursements\Http\Controllers\ExpenseCategoryController;
use App\Modules\Reimbursements\Http\Controllers\PrsReimbursementRequestController;

Route::post('/prs-requests', PrsReimbursementRequestController::class)->middleware('auth.prs-reimbursement-api');

Route::middleware(['auth.external'])->group(function () {
    Route::get('/', [ReimbursementController::class, 'index']);
    Route::post('/', [ReimbursementController::class, 'store']);

    // Receipts
    Route::get('/receipts', [ReceiptController::class, 'index']);
    Route::post('/receipts', [ReceiptController::class, 'store']);
    Route::patch('/receipts/{id}', [ReceiptController::class, 'update']);
    Route::delete('/receipts/{id}', [ReceiptController::class, 'destroy']);

    // Expense Categories
    Route::get('/categories', [ExpenseCategoryController::class, 'index']);

    // Dynamic routes must be at the bottom
    Route::get('/{id}', [ReimbursementController::class, 'show']);
    Route::patch('/{id}', [ReimbursementController::class, 'update']);
    Route::post('/{id}/approve', [ReimbursementController::class, 'approve']);
    Route::post('/{id}/reject', [ReimbursementController::class, 'reject']);
});
