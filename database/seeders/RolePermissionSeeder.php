<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /** group => [name => label] */
    public const PERMISSIONS = [
        'catalog' => [
            'websites.view' => 'View websites',
            'websites.review' => 'Approve and reject websites',
            'websites.edit' => 'Edit website details',
        ],
        'trading' => [
            'orders.view' => 'View orders',
            'orders.refund' => 'Refund orders',
            'posts.view' => 'View posts',
            'posts.moderate' => 'Move posts through the lifecycle',
        ],
        'money' => [
            'wallets.view' => 'View wallets',
            'wallets.adjust' => 'Adjust wallet balances',
            'payouts.release' => 'Release publisher payouts',
            'invoices.view' => 'View invoices',
        ],
        'people' => [
            'users.view' => 'View advertisers',
            'users.suspend' => 'Suspend advertisers',
            'admins.manage' => 'Manage staff accounts',
        ],
        'system' => [
            'settings.manage' => 'Change settings',
            'audit.view' => 'Read the audit log',
        ],
    ];

    /** role => permission names, or ['*'] for everything. */
    public const ROLES = [
        'owner' => ['label' => 'Owner', 'permissions' => ['*']],
        'moderator' => [
            'label' => 'Moderator',
            'permissions' => ['websites.view', 'websites.review', 'websites.edit', 'posts.view', 'posts.moderate'],
        ],
        'finance' => [
            'label' => 'Finance',
            'permissions' => ['orders.view', 'orders.refund', 'wallets.view', 'wallets.adjust', 'payouts.release', 'invoices.view'],
        ],
        'support' => [
            'label' => 'Support',
            'permissions' => ['websites.view', 'orders.view', 'posts.view', 'users.view'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::query()->updateOrCreate(['name' => $name], ['label' => $label, 'group' => $group]);
            }
        }

        foreach (self::ROLES as $name => $definition) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => $definition['label']]);

            $ids = $definition['permissions'] === ['*']
                ? Permission::query()->pluck('id')
                : Permission::query()->whereIn('name', $definition['permissions'])->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
