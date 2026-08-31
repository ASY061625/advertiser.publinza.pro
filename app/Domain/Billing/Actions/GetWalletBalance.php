<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Wallet;
use App\Models\User;

final class GetWalletBalance
{
    /** Spendable balance — what the header chip shows. */
    public function handle(User $user): int
    {
        $wallet = Wallet::query()->firstWhere('user_id', $user->id);

        return $wallet?->availableMinorUnits() ?? 0;
    }
}
