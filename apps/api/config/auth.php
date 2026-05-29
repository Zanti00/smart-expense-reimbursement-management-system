<?php

use App\Modules\Users\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" for the
    | application. SERMS uses external JWT-based authentication via the
    | capstone-auth-module. The auth.external middleware handles token
    | validation — Laravel's built-in guards are not used for auth flow.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | SERMS delegates authentication to the capstone-auth-module.
    | The session guard is retained for potential admin/internal tooling
    | but is not used in the primary request pipeline.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | Points to the modular User model that serves as a local profile cache.
    | User records are provisioned/synced by the auth.external middleware
    | when processing JWT claims from the capstone-auth-module.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

];
