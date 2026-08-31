<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Admin\Models\Admin;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Horizon is staff-only and sits behind the same admin guard as /asylogin.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?Admin $admin = null): bool {
            return auth('admin')->check();
        });
    }
}
