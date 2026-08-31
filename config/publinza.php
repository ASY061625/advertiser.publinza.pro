<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Surface domains
    |--------------------------------------------------------------------------
    |
    | Each of the three surfaces is bound to a hostname in bootstrap/app.php.
    | Keeping them here means local development can point all three at
    | localhost without touching the route files.
    |
    */

    'domains' => [
        'marketing' => env('MARKETING_DOMAIN', 'publinza.pro'),
        'app' => env('APP_DOMAIN', 'app.publinza.pro'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin path
    |--------------------------------------------------------------------------
    |
    | The admin panel is served from an unlisted path on the apex domain. It is
    | configurable so it can be rotated without a code change.
    |
    */

    'admin_prefix' => env('ADMIN_PATH_PREFIX', 'asylogin'),

    'force_https' => (bool) env('FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    */

    'catalog' => [
        'per_page' => (int) env('CATALOG_PER_PAGE', 50),
        // How long the quant-bar min/max ranges stay cached.
        'ranges_ttl_minutes' => (int) env('CATALOG_RANGES_TTL', 60),
    ],

    'billing' => [
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'minimum_top_up_minor_units' => (int) env('BILLING_MIN_TOP_UP', 1000),
    ],
];
