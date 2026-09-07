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

    /*
    |--------------------------------------------------------------------------
    | Company details
    |--------------------------------------------------------------------------
    |
    | Ours, as they appear on an invoice. Config rather than a database row
    | because these change when the company changes, which is a deploy, not a
    | form somebody fills in.
    |
    */

    'company' => [
        'name' => env('COMPANY_NAME', 'Publinza'),
        'address' => env('COMPANY_ADDRESS', 'Publinza OÜ, Sepapaja 6, 15551 Tallinn, Estonia'),
        'vat' => env('COMPANY_VAT', 'VAT EE102938475'),
        'email' => env('COMPANY_EMAIL', 'billing@publinza.pro'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Which PaymentGateway implementation is bound. `simulated` moves no money
    | and returns a deterministic outcome per amount, which is what makes the
    | decline copy testable; `stripe` is the real thing and needs live keys.
    |
    | The bank details are what a transfer top-up displays. They are shown to
    | the payer along with a unique reference, and an admin matches what arrives
    | against that reference by hand.
    |
    */

    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'simulated'),

        // The smallest top-up worth processing: below this the card fee eats it.
        'minimum_top_up_cents' => (int) env('PAYMENTS_MIN_TOP_UP', 5000),

        'quick_amounts_cents' => [10_000, 25_000, 50_000, 100_000, 250_000],

        'bank' => [
            'beneficiary' => env('BANK_BENEFICIARY', 'Publinza OÜ'),
            'iban' => env('BANK_IBAN', 'EE38 2200 2210 6789 1234'),
            'bic' => env('BANK_BIC', 'HABAEE2X'),
            'bank_name' => env('BANK_NAME', 'Swedbank AS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocation
    |--------------------------------------------------------------------------
    |
    | Which request header carries the client's country, set by the edge.
    | Cloudflare sends CF-IPCountry; most load balancers have an equivalent.
    | Empty disables it, and every screen that shows a location says "Unknown"
    | rather than guessing — a location that is quietly wrong is worse than one
    | that is honestly missing, because the point of showing it is for somebody
    | to spot a session that is not theirs.
    |
    */

    'geolocation' => [
        'country_header' => env('GEO_COUNTRY_HEADER', 'CF-IPCountry'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    |
    | What the profile's API tab tells people. The rate limit is read from here
    | by both the page and the limiter, so a screen promising 120 requests a
    | minute cannot outlive a change to 60.
    |
    */

    'api' => [
        'base_url' => env('API_BASE_URL', 'https://api.publinza.pro/v1'),
        'docs_url' => env('API_DOCS_URL', 'https://docs.publinza.pro/api'),
        'rate_limit' => (int) env('API_RATE_LIMIT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        // An avatar is cropped square in the browser before it is sent, so this
        // is a ceiling on what a hostile client could post, not a size anybody
        // legitimately hits.
        'avatar_max_kb' => (int) env('AVATAR_MAX_KB', 2048),
        'logo_max_kb' => (int) env('LOGO_MAX_KB', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support hours
    |--------------------------------------------------------------------------
    |
    | When the team is at their desks, in UTC, and how long a reply typically
    | takes outside those hours. The composer says so rather than leaving
    | somebody who wrote at midnight wondering whether the message sent.
    |
    | Days are ISO-8601: 1 is Monday, 7 is Sunday.
    |
    */

    'support' => [
        'timezone' => env('SUPPORT_TIMEZONE', 'UTC'),
        'open_hour' => (int) env('SUPPORT_OPEN_HOUR', 8),
        'close_hour' => (int) env('SUPPORT_CLOSE_HOUR', 18),
        'days' => [1, 2, 3, 4, 5],
        'response_hours' => (int) env('SUPPORT_RESPONSE_HOURS', 4),
    ],
];
