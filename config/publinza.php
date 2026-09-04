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

    /*
    |--------------------------------------------------------------------------
    | Advertiser app URL
    |--------------------------------------------------------------------------
    |
    | Every "Log in" and "Create account" link on the marketing site points
    | here. Kept as a full URL because the two surfaces are different hosts.
    |
    */

    'app_url' => env('APP_SUBDOMAIN_URL', 'https://app.publinza.pro'),

    'force_https' => (bool) env('FORCE_HTTPS', false),

    // Shown at the foot of the app sidebar. Set from the release tag on deploy.
    'version' => env('APP_VERSION', 'dev'),

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

    /*
    |--------------------------------------------------------------------------
    | Competitors
    |--------------------------------------------------------------------------
    |
    | The SEO benchmarking tab. `provider` picks which vendor answers for every
    | domain in every project — one vendor at a time, because a delta between
    | two vendors' figures for the same measure is not a measurement of
    | anything. Set it to ahrefs, semrush, moz or dataforseo and give that
    | vendor its credentials in config/services.php; with none configured the
    | tab runs on clearly-labelled sample data rather than an error screen.
    |
    */

    'competitors' => [
        'provider' => env('COMPETITOR_METRICS_PROVIDER', 'sample'),

        // How many rivals one project can track. The tab is a comparison, and a
        // comparison of thirty things is a spreadsheet.
        'max_per_project' => (int) env('COMPETITOR_MAX_PER_PROJECT', 10),

        // How long a fetched row stands before it is refetched.
        'cache_days' => (int) env('COMPETITOR_CACHE_DAYS', 7),

        // How long a person must wait between manual refreshes of one
        // competitor. Vendor calls are metered and billed per row.
        'refresh_cooldown_hours' => (int) env('COMPETITOR_REFRESH_COOLDOWN', 24),

        'gap_keywords' => (int) env('COMPETITOR_GAP_KEYWORDS', 100),

        'referring_domains' => (int) env('COMPETITOR_REFERRING_DOMAINS', 500),

        // How many suggestion cards the recommendation strip can show.
        'recommendations' => (int) env('COMPETITOR_RECOMMENDATIONS', 5),
    ],

    'billing' => [
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'minimum_top_up_minor_units' => (int) env('BILLING_MIN_TOP_UP', 1000),
    ],
];
