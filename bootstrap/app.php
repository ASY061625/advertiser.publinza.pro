<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdminIsAuthenticated;
use App\Http\Middleware\EnsureTwoFactorIsConfirmed;
use App\Http\Middleware\HandleAdminInertiaRequests;
use App\Http\Middleware\HandleAdvertiserInertiaRequests;
use App\Http\Middleware\SecureAdminHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // 1. Marketing site — publinza.pro, public. Server-rendered Blade,
            //    so there is no Inertia middleware on this surface at all.
            Route::middleware('web')
                ->domain(config('publinza.domains.marketing'))
                ->group(base_path('routes/marketing.php'));

            // 2. Advertiser app — app.publinza.pro, `web` guard.
            //    AuthenticateSession ties each session to the password hash it
            //    was created under, so changing a password — including through
            //    a reset — signs every other session out. That is the mechanism
            //    behind "you have been signed out everywhere else", and it works
            //    with the Redis session driver, which a DELETE on the sessions
            //    table would not.
            Route::middleware(['web', AuthenticateSession::class, HandleAdvertiserInertiaRequests::class])
                ->domain(config('publinza.domains.app'))
                ->group(base_path('routes/app.php'));

            // 3. Admin panel — publinza.pro/asylogin, `admin` guard behind 2FA.
            //    The login and two-factor screens opt out of the guards they
            //    exist to satisfy; see routes/admin.php.
            Route::middleware([
                'web',
                SecureAdminHeaders::class,
                HandleAdminInertiaRequests::class,
                'admin',
                '2fa',
                'throttle:10,1',
            ])
                ->domain(config('publinza.domains.marketing'))
                ->prefix(config('publinza.admin_prefix'))
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdminIsAuthenticated::class,
            '2fa' => EnsureTwoFactorIsConfirmed::class,
        ]);

        $middleware->trustProxies(at: '*');

        // Inertia turns a 419 into a readable "your session expired" redirect.
        $middleware->validateCsrfTokens(except: ['stripe/*', 'webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response) {
            // Error pages are rendered by the surface the request landed on.
            return $response;
        });
    })
    ->create();
