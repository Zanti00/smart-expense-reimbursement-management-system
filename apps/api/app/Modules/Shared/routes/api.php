<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('auth.external')->group(function () {
    Route::get('/auth/me', function (\Illuminate\Http\Request $request) {
        return response()->json($request->user());
    });

    Route::post('/auth/verify-password', function (\Illuminate\Http\Request $request) {
        $password = $request->input('password');
        $valid = \App\Modules\Shared\Services\PasswordVerificationService::verify($request, $password);
        return response()->json(['valid' => $valid]);
    });
});
