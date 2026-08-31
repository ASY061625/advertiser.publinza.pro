<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => (bool) env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    'connection' => env('SESSION_CONNECTION', 'default'),
    'store' => env('SESSION_STORE'),
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', 'publinza_session'),
    'path' => '/',

    /*
    | Set to `.publinza.pro` so a session is shared between the apex and the app
    | subdomain. The admin guard still has its own session key, so sharing the
    | cookie does not share authentication between the two guards.
    */
    'domain' => env('SESSION_DOMAIN'),

    'secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
    'partitioned' => false,
];
