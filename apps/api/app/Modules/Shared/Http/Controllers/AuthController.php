<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Services\PasswordVerificationService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function verifyPassword(Request $request)
    {
        $valid = PasswordVerificationService::verify(
            $request,
            (string) $request->input('password')
        );

        return response()->json(['valid' => $valid]);
    }
}
