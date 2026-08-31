<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\Models\Wallet;
use App\Domain\Billing\Policies\WalletPolicy;
use App\Domain\Catalog\Models\Site;
use App\Domain\Catalog\Policies\SitePolicy;
use App\Domain\Messaging\Models\Thread;
use App\Domain\Messaging\Policies\ThreadPolicy;
use App\Domain\Posts\Models\Post;
use App\Domain\Posts\Policies\PostPolicy;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Policies live next to the models they guard, one per domain.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Site::class => SitePolicy::class,
        Project::class => ProjectPolicy::class,
        Post::class => PostPolicy::class,
        Wallet::class => WalletPolicy::class,
        Thread::class => ThreadPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Admin abilities are role checks on the Admin record itself, so they
        // are registered as gates rather than model policies.
        Gate::define('reviewSites', [\App\Domain\Admin\Policies\AdminPolicy::class, 'reviewSites']);
        Gate::define('refundOrders', [\App\Domain\Admin\Policies\AdminPolicy::class, 'refundOrders']);
        Gate::define('releasePayouts', [\App\Domain\Admin\Policies\AdminPolicy::class, 'releasePayouts']);
        Gate::define('manageAdmins', [\App\Domain\Admin\Policies\AdminPolicy::class, 'manageAdmins']);
    }
}
