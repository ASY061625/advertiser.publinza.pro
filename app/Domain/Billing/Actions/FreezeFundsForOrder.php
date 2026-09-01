<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Wallet;
use App\Domain\Trading\Models\Order;
use App\Models\User;
use RuntimeException;

/**
 * Checkout commits the order total rather than spending it. The publisher is
 * paid out of the frozen amount once the post is verified as live.
 */
final class FreezeFundsForOrder
{
    public function handle(User $user, Order $order): Wallet
    {
        /** @var Wallet|null $wallet */
        $wallet = Wallet::query()->firstWhere('user_id', $user->id);

        if ($wallet === null) {
            throw new RuntimeException("Advertiser #{$user->id} has no wallet to charge.");
        }

        // Throws InsufficientFunds if the balance cannot cover it. The check
        // happens under the row lock inside freeze(), not here.
        $wallet->freeze($order->total(), $order, "Order {$order->order_number}");

        return $wallet->refresh();
    }
}
