<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Notifications\Http\Controllers\NotificationController;

Route::middleware('auth.external')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
});
