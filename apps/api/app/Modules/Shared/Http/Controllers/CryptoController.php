<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Services\PayloadDecryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Serves the SERMS server RSA public key so the SPA can encrypt sensitive
 * payloads client-side (AES-256-GCM + RSA-OAEP wrapper, per SDD §5).
 *
 * This endpoint is intentionally public (no auth.external middleware) because
 * the public key is, by design, not secret and is fetched during session init.
 */
class CryptoController extends Controller
{
    public function publicKey(Request $request)
    {
        try {
            $pem = PayloadDecryptionService::getPublicKeyPem();

            return response()->json([
                'public_key' => $pem,
                'algorithm' => 'RSA-OAEP',
                'hash' => 'SHA-256',
                'key_size' => 2048,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to serve crypto public key.', [
                'error' => $e->getMessage(),
            ]);
            throw $e; // rethrow per AGENTS.md — never silently swallow
        }
    }
}
