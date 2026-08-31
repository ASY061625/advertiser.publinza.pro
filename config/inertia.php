<?php

declare(strict_types=1);

return [
    /*
    | Server-side rendering is off: the admin panel must never share a render
    | process with the public surfaces, and the marketing pages are small enough
    | to prerender at the CDN instead.
    */
    'ssr' => [
        'enabled' => false,
        'url' => 'http://127.0.0.1:13714',
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/marketing/Pages'),
            resource_path('js/advertiser/Pages'),
            resource_path('js/admin/Pages'),
        ],
        'page_extensions' => ['tsx'],
    ],
];
