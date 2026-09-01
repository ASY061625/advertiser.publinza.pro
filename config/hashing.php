<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default hash driver
    |--------------------------------------------------------------------------
    |
    | Argon2id, not bcrypt. It resists both GPU and side-channel attacks, has no
    | 72-byte silent truncation, and is what OWASP recommends first for new
    | applications.
    |
    | Changing these parameters does not invalidate existing hashes: the
    | algorithm and cost are encoded in each hash, and Laravel rehashes on the
    | next successful sign-in.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

    /*
    | 64 MiB and 4 passes across 2 threads: roughly 100ms on the production
    | tier, which is slow enough to matter to an attacker and fast enough that
    | sign-in does not feel it. Tests override these — see phpunit.xml — because
    | a real Argon2 cost makes a factory that creates fifty users unbearable.
    */
    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 2),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => true,
    ],
];
