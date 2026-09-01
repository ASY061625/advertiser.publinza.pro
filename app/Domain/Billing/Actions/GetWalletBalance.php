<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Wallet;
use App\Models\User;

final class GetWalletBalance
{
    /** Spendable balance — what the header chip shows. */
    public function handle(User $user): Money
    {
        return Wallet::query()->firstWhere('user_id', $user->id)?->available() ?? Money::zero();
    }
}
