<?php

namespace App\Modules\Shared\Http\Middleware;

use App\Modules\Shared\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->has('__idempotency_processed')) {
            return $next($request);
        }

        $method = strtoupper($request->method());

        // 1. Safe methods bypass idempotency inspection
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }

        // 2. Extract idempotency key header
        $key = $request->header('X-Idempotency-Key') ?? $request->header('Idempotency-Key');

        if (empty($key)) {
            return $next($request);
        }

        $request->attributes->set('__idempotency_processed', true);

        $userId = $request->user()?->id;
        $path = $request->path();
        $requestHash = $this->computeRequestHash($method, $path, $request->all());

        // 3. Acquire atomic pending lock or check existing record
        $existing = IdempotencyKey::where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing) {
            if ($existing->status === 'completed') {
                if ($existing->request_hash !== $requestHash) {
                    return response()->json([
                        'message' => 'Idempotency key reused with different request payload.',
                    ], 422);
                }

                $headers = $existing->response_headers ?? ['Content-Type' => 'application/json'];
                $headers['X-Idempotent-Replay'] = 'true';

                return response($existing->response_body, $existing->response_status, $headers);
            }

            if ($existing->status === 'pending') {
                return response()->json([
                    'message' => 'A request with this idempotency key is currently processing. Please wait.',
                ], 409);
            }
        }

        // 4. Insert pending record
        try {
            $ledger = IdempotencyKey::create([
                'user_id' => $userId,
                'idempotency_key' => $key,
                'request_method' => $method,
                'request_path' => $path,
                'request_hash' => $requestHash,
                'status' => 'pending',
                'expires_at' => now()->addHours(24),
            ]);
        } catch (QueryException $e) {
            // Caught concurrent insert race condition
            return response()->json([
                'message' => 'A request with this idempotency key is currently processing. Please wait.',
            ], 409);
        }

        // 5. Execute pipeline
        try {
            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                $ledger->update([
                    'status' => 'completed',
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'response_headers' => [
                        'Content-Type' => $response->headers->get('Content-Type') ?? 'application/json',
                    ],
                ]);
            } else {
                $ledger->delete();
            }

            return $response;
        } catch (\Throwable $e) {
            $ledger->delete();
            throw $e;
        }
    }

    /**
     * Compute deterministic hash for request path and body.
     */
    protected function computeRequestHash(string $method, string $path, array $payload): string
    {
        ksort($payload);
        return hash('sha256', $method . '|' . $path . '|' . json_encode($payload));
    }
}
