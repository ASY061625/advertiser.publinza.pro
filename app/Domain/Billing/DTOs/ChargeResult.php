<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use App\Domain\Billing\Enums\DeclineReason;

/**
 * What the gateway did.
 *
 * A decline is a successful call with an unsuccessful outcome, which is why the
 * reason is a value here rather than an exception: the caller has to record it,
 * show it and decide whether "Try again" is honest.
 */
final readonly class ChargeResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $reference = null,
        public ?DeclineReason $reason = null,
    ) {}

    public static function succeeded(string $reference): self
    {
        return new self(true, $reference);
    }

    public static function declined(DeclineReason $reason): self
    {
        return new self(false, null, $reason);
    }
}
