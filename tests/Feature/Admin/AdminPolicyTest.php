<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

function staff(string $role, string $status = 'active'): Admin
{
    return Admin::query()->create([
        'email' => $role.'-'.uniqid().'@publinza.test',
        'name' => ucfirst($role),
        'password' => Hash::make('Correct-Horse-9'),
        'role_id' => Role::query()->where('name', $role)->firstOrFail()->id,
        'status' => $status,
    ]);
}

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('lets the owner do everything', function (): void {
    $owner = staff('owner');

    foreach (['reviewSites', 'refundOrders', 'releasePayouts', 'manageAdmins'] as $ability) {
        expect(Gate::forUser($owner)->allows($ability, $owner))->toBeTrue();
    }
});

it('grants each role exactly the abilities its permissions cover', function (): void {
    $moderator = staff('moderator');
    $finance = staff('finance');
    $support = staff('support');

    expect(Gate::forUser($moderator)->allows('reviewSites', $moderator))->toBeTrue()
        ->and(Gate::forUser($moderator)->allows('refundOrders', $moderator))->toBeFalse()
        ->and(Gate::forUser($moderator)->allows('manageAdmins', $moderator))->toBeFalse();

    expect(Gate::forUser($finance)->allows('refundOrders', $finance))->toBeTrue()
        ->and(Gate::forUser($finance)->allows('releasePayouts', $finance))->toBeTrue()
        ->and(Gate::forUser($finance)->allows('reviewSites', $finance))->toBeFalse();

    expect(Gate::forUser($support)->allows('reviewSites', $support))->toBeFalse()
        ->and(Gate::forUser($support)->allows('refundOrders', $support))->toBeFalse();
});

it('strips every ability from a suspended admin, owner included', function (): void {
    $owner = staff('owner', 'suspended');

    expect(Gate::forUser($owner)->allows('reviewSites', $owner))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('manageAdmins', $owner))->toBeFalse();
});
