<?php

declare(strict_types=1);

use App\Domain\Billing\DTOs\Money;

it('parses major units exactly, without float drift', function (): void {
    expect(Money::fromMajorUnits('19.99')->cents)->toBe(1999)
        ->and(Money::fromMajorUnits('0.10')->cents)->toBe(10)
        ->and(Money::fromMajorUnits('1234.05')->cents)->toBe(123405)
        ->and(Money::fromMajorUnits('100')->cents)->toBe(10000)
        // The float path rounds rather than truncating: (int) (19.99 * 100) is 1998.
        ->and(Money::fromMajorUnits(19.99)->cents)->toBe(1999);
});

it('round-trips through major units', function (): void {
    foreach ([0, 5, 99, 100, 1999, 123405, 999999999] as $cents) {
        expect(Money::fromMajorUnits((new Money($cents))->toMajorUnits())->cents)->toBe($cents);
    }
});

it('handles negative amounts', function (): void {
    expect(Money::fromMajorUnits('-19.99')->cents)->toBe(-1999)
        ->and((new Money(-1999))->toMajorUnits())->toBe('-19.99');
});

it('rejects an unparseable amount', function (): void {
    expect(fn () => Money::fromMajorUnits('twelve dollars'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::fromMajorUnits('1.234'))->toThrow(InvalidArgumentException::class);
});

it('adds and subtracts without drift', function (): void {
    $total = Money::zero();

    // A float accumulator drifts off 0.10 within a few hundred additions.
    for ($i = 0; $i < 1000; $i++) {
        $total = $total->plus(Money::fromMajorUnits('0.10'));
    }

    expect($total->cents)->toBe(100_00)
        ->and($total->toMajorUnits())->toBe('100.00');
});

it('refuses to mix currencies', function (): void {
    expect(fn () => (new Money(1000))->plus(new Money(1000, 'EUR')))
        ->toThrow(InvalidArgumentException::class);
});

it('formats for display', function (): void {
    expect((new Money(123405))->format())->toBe('$1,234.05')
        ->and((new Money(0))->format())->toBe('$0.00');
});
