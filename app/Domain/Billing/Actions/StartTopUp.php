<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\TopUpMethod;
use App\Domain\Billing\Enums\TopUpStatus;
use App\Domain\Billing\Models\PaymentMethod;
use App\Domain\Billing\Models\TopUp;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Billing\Support\VolumeBonus;
use App\Events\ShellCountsChanged;
use App\Models\User;
use App\Notifications\Publinza\TopUpConfirmedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Adds funds to a wallet, or records why it could not.
 *
 * The order of operations is the whole design. The gateway is called *outside*
 * the database transaction, because a payment provider is a network call that
 * can take seconds and must never sit inside a row lock on the wallet — a
 * transaction held open across an HTTP round trip is how a busy account
 * deadlocks. What happens after the money is taken is one transaction.
 *
 * A bank transfer never reaches the gateway at all: it is a promise to send
 * money, and crediting it before the money arrives would let anyone type
 * themselves a balance.
 */
final class StartTopUp
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly VolumeBonus $bonus,
    ) {}

    public function handle(
        User $user,
        Money $amount,
        TopUpMethod $method,
        ?int $paymentMethodId = null,
        ?string $token = null,
    ): TopUp {
        // Server-side, always. The summary box shows what this returns and the
        // deposit writes what this returns — a bonus computed in the browser is
        // a bonus somebody can edit before sending.
        $bonus = $this->bonus->for($amount);

        $card = $paymentMethodId === null
            ? null
            : PaymentMethod::query()->where('user_id', $user->id)->find($paymentMethodId);

        $topUp = TopUp::query()->create([
            'user_id' => $user->id,
            'payment_method_id' => $card?->id,
            'method' => $method,
            'amount_cents' => $amount->cents,
            'bonus_cents' => $bonus->cents,
            'status' => TopUpStatus::Pending,
            'reference' => TopUp::newReference(),
        ]);

        if (! $method->isInstant()) {
            // Nothing more to do. The row stays pending, the page shows the
            // account details and this reference, and an admin matches the
            // incoming payment to it by hand.
            return $topUp;
        }

        $result = $this->gateway->charge($user, $amount, $method, $token ?? $card?->provider_reference);

        if (! $result->succeeded) {
            $topUp->update([
                'status' => TopUpStatus::Failed,
                'decline_code' => $result->reason,
            ]);

            return $topUp->refresh();
        }

        $this->credit($user, $topUp, $amount, $bonus, $result->reference);

        $topUp->refresh();

        $user->notify(TopUpConfirmedNotification::for($topUp));

        return $topUp;
    }

    /**
     * Confirms a bank transfer that has arrived.
     *
     * Idempotent on status: a payment confirmed twice by two admins clicking at
     * once must credit the balance once.
     */
    public function confirm(TopUp $topUp): TopUp
    {
        if ($topUp->status !== TopUpStatus::Pending) {
            return $topUp;
        }

        $user = $topUp->user;

        if ($user === null) {
            return $topUp;
        }

        $this->credit(
            $user,
            $topUp,
            Money::fromCents($topUp->amount_cents),
            Money::fromCents($topUp->bonus_cents),
            null,
        );

        $topUp->refresh();

        $user->notify(TopUpConfirmedNotification::for($topUp));

        return $topUp;
    }

    /**
     * The money, the bonus and the receipt — all or none of it.
     *
     * Two ledger rows rather than one of the sum: the deposit is what the
     * advertiser paid and the bonus is what we gave them, and a statement that
     * cannot tell those apart cannot answer "how much of this did I buy".
     */
    private function credit(User $user, TopUp $topUp, Money $amount, Money $bonus, ?string $reference): void
    {
        DB::transaction(function () use ($user, $topUp, $amount, $bonus, $reference): void {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['available_cents' => 0, 'frozen_cents' => 0, 'currency' => $amount->currency],
            );

            $wallet->deposit($amount, $topUp, "Top-up {$topUp->reference}");

            if ($bonus->isPositive()) {
                $wallet->bonus($bonus, $topUp, "Volume bonus on {$topUp->reference}");
            }

            $topUp->update([
                'status' => TopUpStatus::Succeeded,
                'provider_reference' => $reference,
                'confirmed_at' => now(),
            ]);
        });

        ShellCountsChanged::dispatch($user, ['balance']);
    }
}
