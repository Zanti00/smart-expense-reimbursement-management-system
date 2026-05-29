<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('auth.external')->group(function () {
    Route::get('/auth/me', function (\Illuminate\Http\Request $request) {
        return response()->json($request->user());
    });
});
