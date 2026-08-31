<?php

declare(strict_types=1);

use App\Domain\Billing\DTOs\Money;

it('converts major units without float drift', function (): void {
    expect(Money::fromMajorUnits('19.99')->minorUnits)->toBe(1999)
        ->and(Money::fromMajorUnits(0.1 + 0.2)->minorUnits)->toBe(30);
});

it('refuses to mix currencies', function (): void {
    $usd = new Money(1000);
    $eur = new Money(1000, 'EUR');

    expect(fn () => $usd->plus($eur))->toThrow(InvalidArgumentException::class);
});
