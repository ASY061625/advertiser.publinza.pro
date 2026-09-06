<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum TopUpStatus: string
{
    /** Awaiting the money — a bank transfer that has not arrived yet. */
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Succeeded => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
