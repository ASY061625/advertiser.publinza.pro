<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Website;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;

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

            /*
             * The three per-account indexes the global palette reads.
             *
             * `user_id` is filterable on all three and searchable on none. The
             * palette's multi-search sends `user_id = N` with every one of
             * these queries, and Meilisearch fails an unknown filter *quietly*
             * — a missing entry here would not error, it would return
             * everybody's projects to everybody. GlobalSearchTest asserts the
             * scoping for exactly that reason.
             */
            Project::class => [
                'filterableAttributes' => ['user_id'],
                'searchableAttributes' => ['name', 'website_url'],
            ],

            Post::class => [
                'filterableAttributes' => ['user_id'],
                'searchableAttributes' => ['anchor_text', 'domain', 'target_url'],
            ],

            Conversation::class => [
                'filterableAttributes' => ['user_id'],
                'searchableAttributes' => ['subject', 'domain'],
            ],
        ],
    ],
];
