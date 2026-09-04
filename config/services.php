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

    /*
    | The four SEO vendors the competitors tab can read from. Which one is
    | actually used is `publinza.competitors.provider`; these are only the
    | credentials for whichever that names.
    */

    'moz' => [
        'access_id' => env('MOZ_ACCESS_ID'),
        'secret_key' => env('MOZ_SECRET_KEY'),
    ],

    'semrush' => [
        'key' => env('SEMRUSH_API_KEY'),
        'database' => env('SEMRUSH_DATABASE', 'us'),
    ],

    'dataforseo' => [
        'login' => env('DATAFORSEO_LOGIN'),
        'password' => env('DATAFORSEO_PASSWORD'),
        'location_code' => (int) env('DATAFORSEO_LOCATION_CODE', 2840),
        'language_code' => env('DATAFORSEO_LANGUAGE_CODE', 'en'),
    ],
];
