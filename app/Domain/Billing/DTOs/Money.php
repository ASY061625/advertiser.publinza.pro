<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact monetary amount held as an integer number of cents.
 *
 * Every money column in the schema is an UNSIGNED BIGINT of cents with a
 * `_cents` suffix and is cast to this object. No float ever touches money: a
 * float cannot represent 0.10 exactly, and a marketplace that adds thousands of
 * line items with floats will drift.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    public function __construct(
        public int $cents,
        public string $currency = 'USD',
    ) {}

    public static function zero(string $currency = 'USD'): self
    {
        return new self(0, $currency);
    }

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        return new self($cents, $currency);
    }

    /**
     * Parses a major-unit amount ("19.99") into cents.
     *
     * Strings are parsed digit-wise rather than multiplied as floats, because
     * (int) round(19.99 * 100) is right by luck and (int) (19.99 * 100) is 1998.
     */
    public static function fromMajorUnits(int|float|string $amount, string $currency = 'USD'): self
    {
        if (is_string($amount)) {
            if (! preg_match('/^-?\d+(\.\d{1,2})?$/', trim($amount))) {
                throw new InvalidArgumentException("Cannot parse \"{$amount}\" as a money amount.");
            }

            [$whole, $fraction] = array_pad(explode('.', trim($amount), 2), 2, '0');
            $sign = str_starts_with($whole, '-') ? -1 : 1;

            return new self($sign * ((int) abs((int) $whole) * 100 + (int) str_pad($fraction, 2, '0')), $currency);
        }

        return new self((int) round($amount * 100), $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents - $other->cents, $this->currency);
    }

    public function times(int $factor): self
    {
        return new self($this->cents * $factor, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents < $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    /** "1234.50" — for display and for exact re-parsing. */
    public function toMajorUnits(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    public function format(): string
    {
        return match ($this->currency) {
            'USD' => '$'.number_format($this->cents / 100, 2),
            'EUR' => '€'.number_format($this->cents / 100, 2),
            default => number_format($this->cents / 100, 2).' '.$this->currency,
        };
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * @return array{cents: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
