<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;

return [
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),
    'prefix' => env('SCOUT_PREFIX', 'publinza_'),
    'queue' => (bool) env('SCOUT_QUEUE', true),
    'after_commit' => true,
    'chunk' => ['searchable' => 500, 'unsearchable' => 500],
    'soft_delete' => false,
    'identify' => false,

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key' => env('MEILISEARCH_KEY'),

        'index-settings' => [
            // These names must match the keys Website::toSearchableArray()
            // emits. Meilisearch will not filter or sort on an attribute it was
            // never told about, and it fails quietly — so a mismatch here shows
            // up as a filter that simply does nothing.
            Website::class => [
                'filterableAttributes' => [
                    'category_id',
                    'primary_language_id',
                    'country_id',
                    'price_cents',
                    'monthly_traffic',
                    'ahrefs_dr',
                    'moz_da',
                    'spam_score',
                ],
                'sortableAttributes' => ['price_cents', 'monthly_traffic', 'ahrefs_dr'],
                'searchableAttributes' => ['domain', 'title', 'description'],
            ],
        ],
    ],
];
