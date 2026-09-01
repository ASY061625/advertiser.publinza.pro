<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Charge = 'charge';
    case Refund = 'refund';
    case Freeze = 'freeze';
    case Unfreeze = 'unfreeze';
    case Adjustment = 'adjustment';
    case Bonus = 'bonus';

    /** Whether the type increases the wallet's total holdings. */
    public function isCredit(): bool
    {
        return in_array($this, [self::Deposit, self::Refund, self::Bonus], true);
    }

    /** Freeze and unfreeze move money between buckets without changing the total. */
    public function isInternalTransfer(): bool
    {
        return in_array($this, [self::Freeze, self::Unfreeze], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Charge => 'Charge',
            self::Refund => 'Refund',
            self::Freeze => 'Freeze',
            self::Unfreeze => 'Unfreeze',
            self::Adjustment => 'Adjustment',
            self::Bonus => 'Bonus',
        };
    }
}
