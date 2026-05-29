<?php

namespace App\Modules\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithExternalService
{
    /**
     * Handle an incoming request by validating the token via the
     * capstone-auth-module and provisioning a local user profile.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization');

        if (!$authorizationHeader || !str_starts_with($authorizationHeader, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthorized. Bearer token missing.',
            ], 401);
        }

        $token = str_replace('Bearer ', '', $authorizationHeader);
        $token = urldecode($token);

        // Since capstone-auth-module uses Laravel Sanctum (not stateless JWTs),
        // we must call its internal verification endpoint.
        $authUrl = env('AUTH_SERVICE_URL', 'http://auth-service:8000');
        
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->post("{$authUrl}/api/internal/verify-token", [
                'token' => $token
            ]);

            if (!$response->successful() || !$response->json('valid')) {
                return response()->json(['message' => 'Unauthorized. Token verification failed.'], 401);
            }

            $userData = $response->json('user');

            $rawRole = strtolower($userData['role'] ?? '');
            $sermsRole = 'employee';
            if (in_array($rawRole, ['it admin', 'admin', 'super admin'])) {
                $sermsRole = 'admin';
            } elseif (in_array($rawRole, ['manager', 'finance manager', 'approver'])) {
                $sermsRole = 'approver';
            }

            // Find or create the user in the local SERMS database to maintain foreign keys
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
                    'role' => $sermsRole,
                    'department' => $userData['department'] ?? 'General',
                ]
            );

            // Update user details if they changed
            $user->update([
                'name' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),
                'role' => $sermsRole,
                'department' => $userData['department'] ?? 'General',
            ]);

            // Set the authenticated user for the current request without starting a session
            Auth::setUser($user);

            // Associate user profile with current request so $request->user() works
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('External auth verification failed: ' . $e->getMessage());
            return response()->json(['message' => 'Unauthorized. Authentication service unavailable.'], 401);
        }
    }
}
