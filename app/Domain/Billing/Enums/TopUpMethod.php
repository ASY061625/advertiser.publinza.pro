<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum TopUpMethod: string
{
    case SavedCard = 'saved_card';
    case NewCard = 'new_card';
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::SavedCard => 'Saved card',
            self::NewCard => 'New card',
            self::PayPal => 'PayPal',
            self::BankTransfer => 'Bank transfer',
        };
    }

    /**
     * Whether the money lands the moment the request succeeds.
     *
     * A bank transfer does not: it is a promise to send money, matched by hand
     * against what arrives. Treating it as instant would credit a balance
     * nobody has paid into.
     */
    public function isInstant(): bool
    {
        return $this !== self::BankTransfer;
    }
}
