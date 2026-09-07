<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => (int) env('SESSION_LIFETIME', 120),
    'expire_on_close' => false,
    'encrypt' => (bool) env('SESSION_ENCRYPT', true),
    'files' => storage_path('framework/sessions'),
    /*
     * Null, not 'default'.
     *
     * Laravel reads null here as "whichever connection is the default"; the
     * literal string 'default' is looked up as a connection *name* and there is
     * no such entry in config/database.php — so the database session driver
     * could never boot, and the profile's Active sessions list depends on it.
     */
    'connection' => env('SESSION_CONNECTION'),
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
