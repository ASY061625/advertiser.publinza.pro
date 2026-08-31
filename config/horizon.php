<?php

declare(strict_types=1);

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'asylogin/horizon'),
    'use' => 'horizon',
    'prefix' => env('HORIZON_PREFIX', Str::slug((string) env('APP_NAME', 'publinza'), '_').'_horizon:'),

    // Horizon lives under the admin prefix; the gate in HorizonServiceProvider
    // requires an authenticated admin.
    'middleware' => ['web', 'admin', '2fa'],

    'waits' => ['redis:default' => 60],
    'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'recent_failed' => 10080, 'failed' => 10080, 'monitored' => 10080],
    'silenced' => [],
    'metrics' => ['trim_snapshots' => ['job' => 24, 'queue' => 24]],
    'fast_termination' => false,
    'memory_limit' => 128,

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'search', 'mail'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-default' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'local' => [
            'supervisor-default' => ['maxProcesses' => 3],
        ],
    ],
];
