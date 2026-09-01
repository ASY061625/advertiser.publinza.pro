<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Admin\Policies\AdminPolicy;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Billing\Policies\WalletPolicy;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Policies\WebsitePolicy;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Policies\ConversationPolicy;
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
        Website::class => WebsitePolicy::class,
        Project::class => ProjectPolicy::class,
        Post::class => PostPolicy::class,
        Wallet::class => WalletPolicy::class,
        Conversation::class => ConversationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Admin abilities are role checks on the Admin record itself, so they
        // are registered as gates rather than model policies.
        Gate::define('reviewSites', [AdminPolicy::class, 'reviewSites']);
        Gate::define('refundOrders', [AdminPolicy::class, 'refundOrders']);
        Gate::define('releasePayouts', [AdminPolicy::class, 'releasePayouts']);
        Gate::define('manageAdmins', [AdminPolicy::class, 'manageAdmins']);
    }
}
