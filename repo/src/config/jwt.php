<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret Key
    |--------------------------------------------------------------------------
    | HMAC-SHA256 signing key. Must be at least 32 bytes for HS256.
    */
    'secret' => env('JWT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes (minutes) and session policy
    |--------------------------------------------------------------------------
    | All values are environment-driven so the operational policy can
    | be tuned without code changes. Defaults preserve the historical
    | hard-coded values: 30-minute access TTL, 7-day refresh window,
    | 2 concurrent sessions per account.
    */
    'access_ttl'      => (int) env('JWT_ACCESS_TTL', 30),
    'refresh_ttl'     => (int) env('JWT_REFRESH_TTL', 10080),
    'max_sessions'    => (int) env('JWT_MAX_SESSIONS', 2),

    /*
    |--------------------------------------------------------------------------
    | Algorithm
    |--------------------------------------------------------------------------
    */
    'algorithm' => 'HS256',

    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    */
    'issuer' => env('APP_URL', 'http://localhost:8080'),
];
