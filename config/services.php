<?php

declare(strict_types=1);

return [
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-central-1'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    | Injected by the marketing site only after a visitor accepts analytics
    | cookies. Leave empty to ship no analytics at all.
    */
    'analytics' => [
        'script' => env('ANALYTICS_SCRIPT_URL'),
    ],

    'ahrefs' => [
        'token' => env('AHREFS_API_TOKEN'),
    ],

    'moz' => [
        'access_id' => env('MOZ_ACCESS_ID'),
        'secret' => env('MOZ_SECRET_KEY'),
    ],
];
