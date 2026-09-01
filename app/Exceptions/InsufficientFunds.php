<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Domain\Billing\DTOs\Money;
use DomainException;

class InsufficientFunds extends DomainException
{
    public function __construct(
        public readonly Money $requested,
        public readonly Money $availableBalance,
        public readonly string $bucket = 'available',
    ) {
        parent::__construct(sprintf(
            'Cannot take %s from the %s balance of %s.',
            $requested->format(),
            $bucket,
            $availableBalance->format(),
        ));
    }
}
