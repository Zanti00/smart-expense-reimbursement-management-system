<?php

namespace App\Modules\Reimbursements\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePrsReimbursementApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = config('services.prs.reimbursement_api_key')
            ?: getenv('PRS_REIMBURSEMENT_API_KEY')
            ?: ($_ENV['PRS_REIMBURSEMENT_API_KEY'] ?? null)
            ?: ($_SERVER['PRS_REIMBURSEMENT_API_KEY'] ?? null);

        $token = $request->bearerToken();

        if (!$apiKey || !$token || !hash_equals((string) $apiKey, $token)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
