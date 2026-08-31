<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TopUpWallet
{
    public function handle(User $user, Money $amount, string $reference): Wallet
    {
        if ($amount->minorUnits <= 0) {
            throw new InvalidArgumentException('A top-up must be a positive amount.');
        }

        return DB::transaction(function () use ($user, $amount, $reference): Wallet {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance_minor_units' => 0, 'frozen_minor_units' => 0, 'currency' => $amount->currency],
            );

            $wallet->increment('balance_minor_units', $amount->minorUnits);

            Transaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'top_up',
                'amount_minor_units' => $amount->minorUnits,
                'reference_type' => 'payment',
                'reference_id' => $reference,
            ]);

            return $wallet->refresh();
        });
    }
}
