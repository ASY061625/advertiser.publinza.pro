<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FreezeFundsForOrder
{
    /**
     * Checkout freezes the order total rather than spending it. The publisher is
     * paid from the frozen amount once the post goes live.
     */
    public function handle(User $user, Money $amount, int $orderId): Wallet
    {
        return DB::transaction(function () use ($user, $amount, $orderId): Wallet {
            /** @var Wallet|null $wallet */
            $wallet = Wallet::query()->lockForUpdate()->firstWhere('user_id', $user->id);

            if ($wallet === null || $wallet->availableMinorUnits() < $amount->minorUnits) {
                throw new RuntimeException('Not enough available balance to place this order.');
            }

            $wallet->increment('frozen_minor_units', $amount->minorUnits);

            Transaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'freeze',
                'amount_minor_units' => -$amount->minorUnits,
                'reference_type' => 'order',
                'reference_id' => (string) $orderId,
            ]);

            return $wallet->refresh();
        });
    }
}
