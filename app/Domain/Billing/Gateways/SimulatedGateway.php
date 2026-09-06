<?php

declare(strict_types=1);

namespace App\Domain\Billing\Gateways;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\DTOs\ChargeResult;
use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\DeclineReason;
use App\Domain\Billing\Enums\TopUpMethod;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * A gateway that moves no money.
 *
 * This exists so the decline path is a path somebody has actually walked. A
 * payment integration whose only exercised branch is "succeeded" ships copy for
 * eight failure modes that nobody has ever seen rendered, and those eight are
 * exactly the screens a person is reading when they are already annoyed.
 *
 * Outcomes are chosen by the amount, not at random: a test that sometimes
 * passes is not a test, and a demo that sometimes declines is not a demo. The
 * magic amounts mirror the way Stripe's own test cards work.
 */
final class SimulatedGateway implements PaymentGateway
{
    /**
     * The cents that force a particular decline: $100.14 is an expired card,
     * $100.00 goes through.
     *
     * Keyed on the cents rather than the whole amount, and that is a fix rather
     * than a preference. Whole-dollar triggers had to be small numbers to stay
     * out of the way of real amounts, which put every one of them below the $50
     * minimum — so the validator rejected them first and not one decline could
     * actually be reached. Keying on cents makes every reason testable at any
     * size, and leaves the round amounts on the quick-pick chips alone.
     *
     * @var array<int, DeclineReason>
     */
    private const TRIGGERS = [
        13 => DeclineReason::InsufficientFunds,
        14 => DeclineReason::ExpiredCard,
        15 => DeclineReason::IncorrectCvc,
        16 => DeclineReason::IncorrectNumber,
        17 => DeclineReason::CardDeclined,
        18 => DeclineReason::DoNotHonor,
        19 => DeclineReason::ProcessingError,
        20 => DeclineReason::AuthenticationRequired,
        21 => DeclineReason::CurrencyNotSupported,
    ];

    public function charge(User $user, Money $amount, TopUpMethod $method, ?string $token = null): ChargeResult
    {
        $cents = $amount->cents % 100;

        if (isset(self::TRIGGERS[$cents])) {
            return ChargeResult::declined(self::TRIGGERS[$cents]);
        }

        return ChargeResult::succeeded('sim_'.Str::lower(Str::random(24)));
    }

    public function isLive(): bool
    {
        return false;
    }

    public function publishableKey(): ?string
    {
        // Elements needs a real key. Without one the card form says so rather
        // than rendering an input that can never be submitted.
        return config('services.stripe.key') ?: null;
    }
}
