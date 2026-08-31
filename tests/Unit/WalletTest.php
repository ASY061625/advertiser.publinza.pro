<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Wallet;

it('excludes frozen funds from the spendable balance', function (): void {
    $wallet = new Wallet(['balance_minor_units' => 50_000, 'frozen_minor_units' => 20_000]);

    expect($wallet->availableMinorUnits())->toBe(30_000);
});
