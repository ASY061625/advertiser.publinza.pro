<?php

declare(strict_types=1);
use App\Domain\Admin\Models\Admin;
use App\Models\User;

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    |
    | `web` is the advertiser app on app.publinza.pro. `admin` is the panel at
    | publinza.pro/asylogin, backed by a separate table so an advertiser record
    | can never authenticate into staff tooling.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        'admins' => [
            'provider' => 'admins',
            // Its own table: see the migration. Sharing the advertiser table
            // would make a token issued for one guard valid for the other.
            'table' => 'admin_password_reset_tokens',
            'expire' => 15,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
