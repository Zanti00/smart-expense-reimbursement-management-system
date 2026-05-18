<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Users\Models\User;

class AuthenticateWithExternalService
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization');

        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthorized. External JWT or Auth Token missing or invalid.',
            ], 401);
        }

        $token = str_replace('Bearer ', '', $authorizationHeader);

        // In a production application, you would invoke the external OAuth2/OIDC/JWT verification key system.
        // For local development and demonstration, we parse the simulated token 'mock_token_{id}'
        if (!str_starts_with($token, 'mock_token_')) {
            return response()->json([
                'message' => 'Unauthorized. External token verification failed.',
            ], 401);
        }

        $idSuffix = str_replace('mock_token_', '', $token);
        
        // Find or provision local user context based on external ID/email
        // Typically, the external JWT claims contain email, role, etc.
        // We will provision profiles dynamically to support clean testing.
        $user = null;
        if ($idSuffix == '1') {
            $user = User::firstOrCreate(
                ['email' => 'admin@serms.com'],
                ['name' => 'Alex Reyes', 'role' => 'admin', 'grade' => 'EXEC', 'department' => 'FINANCE', 'avatar' => 'AR']
            );
        } else {
            $user = User::firstOrCreate(
                ['email' => 'employee@serms.com'],
                ['name' => 'John Santos', 'role' => 'employee', 'grade' => 'L2', 'department' => 'SALES', 'avatar' => 'JS']
            );
        }

        // Associate user profile with current request
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
