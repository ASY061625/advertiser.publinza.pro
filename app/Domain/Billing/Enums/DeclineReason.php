<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

/**
 * Why a payment did not go through, and what the payer can do about it.
 *
 * The second half is the point. "Your payment failed" tells somebody nothing
 * they did not already know; it produces a retry with the same card and then a
 * support ticket. Every case here names a next step, and the two that a payer
 * genuinely cannot act on say so rather than inventing advice.
 *
 * Codes follow Stripe's decline_code vocabulary, so a real gateway maps onto
 * this without a translation table in the middle.
 */
enum DeclineReason: string
{
    case InsufficientFunds = 'insufficient_funds';
    case ExpiredCard = 'expired_card';
    case IncorrectCvc = 'incorrect_cvc';
    case IncorrectNumber = 'incorrect_number';
    case CardDeclined = 'card_declined';
    case DoNotHonor = 'do_not_honor';
    case ProcessingError = 'processing_error';
    case AuthenticationRequired = 'authentication_required';
    case CurrencyNotSupported = 'currency_not_supported';

    public function headline(): string
    {
        return match ($this) {
            self::InsufficientFunds => 'Your bank says there are not enough funds on that card.',
            self::ExpiredCard => 'That card has expired.',
            self::IncorrectCvc => 'The security code did not match.',
            self::IncorrectNumber => 'That card number is not valid.',
            self::CardDeclined => 'Your bank declined the payment.',
            self::DoNotHonor => 'Your bank declined the payment without giving a reason.',
            self::ProcessingError => 'Something went wrong on the payment network, not on your card.',
            self::AuthenticationRequired => 'Your bank wants to verify this payment with you.',
            self::CurrencyNotSupported => 'That card cannot be charged in US dollars.',
        };
    }

    /** What to actually do. Never "try again later" on its own. */
    public function advice(): string
    {
        return match ($this) {
            self::InsufficientFunds => 'Try a smaller amount, use a different card, or pay by bank transfer — there is no card limit on a transfer.',
            self::ExpiredCard => 'Add the replacement card, or pay with PayPal or bank transfer in the meantime.',
            self::IncorrectCvc => 'Check the three digits on the back of the card and enter them again.',
            self::IncorrectNumber => 'Check the long number on the front of the card and enter it again.',
            self::CardDeclined => 'Banks usually decline for a reason they will tell you but not us. Call the number on the back of the card, or use another payment method.',
            self::DoNotHonor => 'This is almost always a fraud check on the bank’s side. Calling them usually clears it in a minute, or you can pay with PayPal or bank transfer.',
            self::ProcessingError => 'Nothing is wrong with your card. Try again in a minute; if it happens twice, use another method and tell us.',
            self::AuthenticationRequired => 'Start the payment again and complete the check your bank shows you.',
            self::CurrencyNotSupported => 'Use a card that accepts USD, or pay by bank transfer.',
        };
    }

    /**
     * Whether trying the same card again could plausibly work.
     *
     * An expired card will never work; a processing error might. The form uses
     * this to decide whether "Try again" is an honest button or a trap.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::ProcessingError, self::AuthenticationRequired, self::DoNotHonor => true,
            default => false,
        };
    }
}
