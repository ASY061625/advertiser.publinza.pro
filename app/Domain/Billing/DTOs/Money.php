<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $minorUnits,
        public string $currency = 'USD',
    ) {}

    public static function fromMajorUnits(float|string $amount, string $currency = 'USD'): self
    {
        return new self((int) round(((float) $amount) * 100), $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits - $other->minorUnits, $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('Cannot combine '.$this->currency.' with '.$other->currency.'.');
        }
    }
}
