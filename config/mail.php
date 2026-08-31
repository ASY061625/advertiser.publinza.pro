<?php

declare(strict_types=1);

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'mailpit'),
            'port' => (int) env('MAIL_PORT', 1025),
            'encryption' => env('MAIL_ENCRYPTION'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'ses' => ['transport' => 'ses'],
        'log' => ['transport' => 'log', 'channel' => env('MAIL_LOG_CHANNEL')],
        'array' => ['transport' => 'array'],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@publinza.pro'),
        'name' => env('MAIL_FROM_NAME', 'Publinza'),
    ],
];
