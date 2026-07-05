<?php

namespace App\Modules\Reimbursements\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates that an incoming request from the AI service carries the correct bearer token.
 * Uses the same shared-secret pattern as AuthenticatePrsReimbursementApi.
 */
class AuthenticateAiServiceApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.ai_service.api_key')
            ?: getenv('AI_SERVICE_API_KEY')
            ?: ($_ENV['AI_SERVICE_API_KEY'] ?? null)
            ?: ($_SERVER['AI_SERVICE_API_KEY'] ?? null);

        $token = $request->bearerToken();

        if (!$apiKey || !$token || !hash_equals((string) $apiKey, $token)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
