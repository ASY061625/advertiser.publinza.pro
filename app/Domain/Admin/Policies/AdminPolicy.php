<?php

declare(strict_types=1);

namespace App\Domain\Admin\Policies;

use App\Domain\Admin\Models\Admin;

class AdminPolicy
{
    public function reviewSites(Admin $admin): bool
    {
        return in_array($admin->role, ['super', 'moderator'], true);
    }

    public function refundOrders(Admin $admin): bool
    {
        return in_array($admin->role, ['super', 'finance'], true);
    }

    public function releasePayouts(Admin $admin): bool
    {
        return in_array($admin->role, ['super', 'finance'], true);
    }

    public function manageAdmins(Admin $admin): bool
    {
        return $admin->isSuperAdmin();
    }
}
