<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\DTOs\ChargeResult;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\TopUpMethod;
use App\Models\User;

/**
 * The seam between this application and whoever actually moves the money.
 *
 * Deliberately narrow: charge, and describe yourself. Everything the balance
 * page does — the ledger row, the bonus, the invoice, the receipt email — is
 * this application's work and happens either side of one call, so swapping
 * Stripe for anything else touches one class.
 *
 * The binding lives in AppServiceProvider and is chosen by
 * `config('publinza.payments.driver')`.
 */
interface PaymentGateway
{
    /**
     * Takes money. Never throws for a decline — a declined card is an answer,
     * not an exception, and the caller has copy to show for every reason.
     *
     * @param  string|null  $token  A provider token for a new card, or the
     *                              stored reference of a saved one.
     */
    public function charge(User $user, Money $amount, TopUpMethod $method, ?string $token = null): ChargeResult;

    /** Shown in the UI so nobody has to guess whether payments are live. */
    public function isLive(): bool;

    /** For the "new card" form: null when no publishable key is configured. */
    public function publishableKey(): ?string;
}
