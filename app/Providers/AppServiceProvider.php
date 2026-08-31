<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Fail loudly in development rather than silently N+1 in production.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::unguard(false);

        if (app()->isProduction() || config('publinza.force_https')) {
            URL::forceScheme('https');
        }
    }
}
