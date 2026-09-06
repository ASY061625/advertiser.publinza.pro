<?php

declare(strict_types=1);

namespace App\Domain\Billing\Support;

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;

/**
 * Replays the ledger from zero and says what the two buckets should hold.
 *
 * This exists because `SUM(amount_cents)` is *not* the balance, and the reason
 * is worth stating rather than discovering:
 *
 *   `amount_cents` is signed by its effect on the **spendable** balance for a
 *   freeze (−) and an unfreeze (+), and by its effect on **total holdings** for
 *   a charge (−). A charge takes money out of the frozen bucket and leaves the
 *   available balance untouched, so summing the column subtracts every charge
 *   from a number it never touched.
 *
 * Which bucket a row moved money in and out of is carried by its *type*, not by
 * its sign — so reconciling means folding over the types, and anybody who
 * "fixes" the ledger page by summing the column will be wrong by the lifetime
 * total of every completed post. There is a test for exactly that.
 */
final class LedgerReconciler
{
    /**
     * @param  iterable<Transaction>  $transactions
     * @return array{availableCents: int, frozenCents: int}
     */
    public function replay(iterable $transactions): array
    {
        $available = 0;
        $frozen = 0;

        foreach ($transactions as $transaction) {
            // Always the magnitude: the sign lives in the type, and a row's own
            // sign only tells you which bucket the writer had in mind.
            $amount = abs($transaction->amount_cents);

            match ($transaction->type) {
                TransactionType::Deposit,
                TransactionType::Refund,
                TransactionType::Bonus => $available += $amount,

                TransactionType::Freeze => [$available -= $amount, $frozen += $amount],
                TransactionType::Unfreeze => [$frozen -= $amount, $available += $amount],

                // Out of the wallet for good, from the frozen bucket.
                TransactionType::Charge => $frozen -= $amount,

                // The one type that is signed by intent rather than by kind: an
                // adjustment can go either way, so its own sign is the answer.
                TransactionType::Adjustment => $available += $transaction->amount_cents,
            };
        }

        return ['availableCents' => $available, 'frozenCents' => $frozen];
    }

    /**
     * @return array{
     *     reconciles: bool,
     *     replayed: array{availableCents: int, frozenCents: int},
     *     stored: array{availableCents: int, frozenCents: int},
     * }
     */
    public function check(Wallet $wallet): array
    {
        $replayed = $this->replay(
            Transaction::query()
                ->where('wallet_id', $wallet->getKey())
                // The order matters for nothing here — addition commutes — but
                // it makes a failure readable when somebody dumps both lists.
                ->orderBy('id')
                ->cursor(),
        );

        $stored = [
            'availableCents' => $wallet->available_cents,
            'frozenCents' => $wallet->frozen_cents,
        ];

        return [
            'reconciles' => $replayed === $stored,
            'replayed' => $replayed,
            'stored' => $stored,
        ];
    }
}
