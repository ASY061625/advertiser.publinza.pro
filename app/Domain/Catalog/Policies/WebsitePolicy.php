<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Website;
use App\Models\User;

class WebsitePolicy
{
    /** Advertisers only ever see active sites. */
    public function view(User $user, Website $website): bool
    {
        return $website->is_active;
    }

    public function purchase(User $user, Website $website): bool
    {
        return $website->is_active && $user->hasVerifiedEmail() && $user->isActive();
    }
}
