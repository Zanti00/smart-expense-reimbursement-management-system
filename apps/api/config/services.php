<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capstone Auth Module
    |--------------------------------------------------------------------------
    |
    | Configuration for the external capstone-auth-module microservice that
    | handles authentication (login, registration, OTP/MFA, token issuance).
    | SERMS delegates all auth flows to this service and validates JWTs
    | locally using the shared secret.
    |
    */

    'capstone_auth' => [
        'url'        => env('AUTH_SERVICE_URL', 'http://capstone-auth:3000'),
        'jwt_secret' => env('JWT_SECRET'),
    ],

    'prs' => [
        'webhook_secret' => env('PRS_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => env('PRS_WEBHOOK_TOLERANCE_SECONDS', 300),
    ],

];
