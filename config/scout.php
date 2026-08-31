<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Site;

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
            Site::class => [
                'filterableAttributes' => [
                    'category',
                    'language',
                    'price_minor_units',
                    'traffic',
                    'domain_rating',
                    'domain_authority',
                    'spam_score',
                ],
                'sortableAttributes' => ['price_minor_units', 'traffic', 'domain_rating'],
                'searchableAttributes' => ['domain', 'category'],
            ],
        ],
    ],
];
