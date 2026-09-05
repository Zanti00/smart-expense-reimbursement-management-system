<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Services\PasswordVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function verifyPassword(Request $request)
    {
        try {
            // The password arrives as an encrypted envelope; PasswordVerificationService
            // decrypts it internally. Plaintext is no longer read from the body.
            $valid = PasswordVerificationService::verify($request);

            // Contract fix: failure must be signalled with 422 (matching every other
            // password-gated module), not a perpetual HTTP 200.
            return response()->json(['valid' => $valid], $valid ? 200 : 422);
        } catch (ValidationException $e) {
            throw $e; // already a domain 422 — let Laravel render it
        } catch (\Throwable $e) {
            // Integration boundary: log actionable context and rethrow (AGENTS.md rule —
            // never silently swallow).
            Log::error('Password verification integration boundary failure.', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
