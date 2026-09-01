<?php

declare(strict_types=1);

namespace App\Domain\Admin\Policies;

use App\Domain\Admin\Models\Admin;

/**
 * Staff abilities, answered from the permission table.
 *
 * Roles are rows, not strings: an admin's `role` is a Role model, and what a
 * role may do lives in role_permission. Deciding these gates by comparing the
 * role's name against a hard-coded list would put the same policy in two
 * places, and adding a role would silently mean adding it to neither.
 *
 * The owner role bypasses the table, which is the one exception the seeder
 * encodes as `['*']`.
 */
class AdminPolicy
{
    public function reviewSites(Admin $admin): bool
    {
        return $this->allows($admin, 'websites.review');
    }

    public function refundOrders(Admin $admin): bool
    {
        return $this->allows($admin, 'orders.refund');
    }

    public function releasePayouts(Admin $admin): bool
    {
        return $this->allows($admin, 'payouts.release');
    }

    public function manageAdmins(Admin $admin): bool
    {
        return $this->allows($admin, 'admins.manage');
    }

    private function allows(Admin $admin, string $permission): bool
    {
        // A suspended account keeps its role row but loses every ability.
        if ($admin->status !== 'active') {
            return false;
        }

        return $admin->isOwner() || ($admin->role?->hasPermission($permission) ?? false);
    }
}
