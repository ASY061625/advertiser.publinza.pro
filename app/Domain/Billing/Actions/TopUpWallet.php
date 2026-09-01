<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Wallet;
use App\Models\User;

final class TopUpWallet
{
    /** The wallet handles locking and the ledger row; this just finds it. */
    public function handle(User $user, Money $amount, string $reference): Wallet
    {
        /** @var Wallet $wallet */
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['available_cents' => 0, 'frozen_cents' => 0, 'currency' => $amount->currency],
        );

        $wallet->deposit($amount, null, "Top-up {$reference}");

        return $wallet->refresh();
    }
}
