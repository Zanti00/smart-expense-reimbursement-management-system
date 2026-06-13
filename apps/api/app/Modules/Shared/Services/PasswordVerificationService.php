<?php

namespace App\Modules\Shared\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
     * Reusable service to verify user passwords against the external authentication service.
     *
     * Input signature:
     * - Request $request: The incoming request containing JWT authorization headers or cookies.
     * - string $password: The plaintext password string to verify.
     *
     * Returns:
     * - bool: True if verification succeeds, false otherwise.
     *
     * Example Usage:
     * ```php
     * if (!PasswordVerificationService::verify($request, $password)) {
     *     return response()->json(['message' => 'Invalid password'], 422);
     * }
     * ```
     */
class PasswordVerificationService
{
    /**
     * Verify the user's password against the external authentication service.
     */
    public static function verify(Request $request, string $password): bool
    {
        $token = $request->bearerToken();
        
        if (!$token && $request->hasCookie('access_token')) {
            $token = urldecode($request->cookie('access_token'));
        }

        if (!$token) {
            Log::warning('Password verification failed: No active JWT token found in request.');
            return false;
        }

        $authUrl = config('services.capstone_auth.url');
        
        try {
            $headers = [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
            ];
            if ($request->hasHeader('Cookie')) {
                $headers['Cookie'] = $request->header('Cookie');
            }

            $response = Http::withHeaders($headers)->post("{$authUrl}/api/verify-password", [
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['valid']) && $data['valid'] === true;
            }

            Log::error('External password verification returned non-success response.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->status() === 401) {
                $message = $response->json('message') ?? 'Unauthenticated or session missing.';
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'password' => ["Authentication failed: {$message}. Please log out and log back in to refresh your session."]
                ]);
            }

            return false;

        } catch (\Exception $e) {
            Log::error('External password verification request failed.', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
