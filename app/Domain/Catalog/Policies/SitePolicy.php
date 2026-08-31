<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Site;
use App\Models\User;

class SitePolicy
{
    /** Advertisers only ever see approved sites. */
    public function view(User $user, Site $site): bool
    {
        return $site->status === 'approved';
    }

    public function purchase(User $user, Site $site): bool
    {
        return $site->status === 'approved' && $user->hasVerifiedEmail();
    }
}
