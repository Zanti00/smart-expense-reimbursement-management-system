<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\Http::fake([
            '*/api/internal/verify-token' => function ($request) {
                $token = $request['token'] ?? '';
                if (str_starts_with($token, 'mock-')) {
                    $payload = json_decode(base64_decode(substr($token, 5)), true);
                    return \Illuminate\Support\Facades\Http::response([
                        'valid' => true,
                        'user' => $payload
                    ], 200);
                }
                return \Illuminate\Support\Facades\Http::response(['valid' => false], 401);
            }
        ]);
    }

    protected function generateMockToken(array $claims = [])
    {
        $payload = array_merge([
            'email' => 'employee@serms.com',
            'first_name' => 'John',
            'last_name' => 'Santos',
            'role' => 'employee',
            'department' => 'SALES',
        ], $claims);

        return 'mock-' . base64_encode(json_encode($payload));
    }
}
