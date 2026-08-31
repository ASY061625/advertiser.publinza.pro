<?php

declare(strict_types=1);

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Model;

final class ReleasePayout
{
    /**
     * Marks a publisher payout as released. The actual transfer is handled by
     * the payments provider and reconciled by webhook.
     */
    public function handle(Model $payout, Admin $admin): Model
    {
        $payout->forceFill([
            'status' => 'released',
            'released_by' => $admin->id,
            'released_at' => now(),
        ])->save();

        return $payout->refresh();
    }
}
