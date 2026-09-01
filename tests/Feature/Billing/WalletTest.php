<?php

declare(strict_types=1);

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Wallet;
use App\Exceptions\InsufficientFunds;

function makeWallet(int $availableCents = 0, int $frozenCents = 0): Wallet
{
    return Wallet::factory()->create([
        'available_cents' => $availableCents,
        'frozen_cents' => $frozenCents,
    ]);
}

it('deposits into the available balance', function (): void {
    $wallet = makeWallet(1_000);

    $transaction = $wallet->deposit(new Money(5_000));

    expect($wallet->fresh()->available_cents)->toBe(6_000)
        ->and($transaction->type)->toBe(TransactionType::Deposit)
        ->and($transaction->amount_cents)->toBe(5_000)
        ->and($transaction->balance_after_cents)->toBe(6_000);
});

it('moves money from available to frozen on freeze', function (): void {
    $wallet = makeWallet(10_000);

    $transaction = $wallet->freeze(new Money(4_000));
    $wallet->refresh();

    expect($wallet->available_cents)->toBe(6_000)
        ->and($wallet->frozen_cents)->toBe(4_000)
        // The total is unchanged: freezing moves money, it does not spend it.
        ->and($wallet->total()->cents)->toBe(10_000)
        ->and($transaction->amount_cents)->toBe(-4_000)
        ->and($transaction->balance_after_cents)->toBe(6_000)
        ->and($transaction->frozen_after_cents)->toBe(4_000);
});

it('refuses to freeze more than the available balance', function (): void {
    $wallet = makeWallet(3_000);

    expect(fn () => $wallet->freeze(new Money(3_001)))->toThrow(InsufficientFunds::class);

    $wallet->refresh();
    expect($wallet->available_cents)->toBe(3_000)
        ->and($wallet->frozen_cents)->toBe(0)
        // A refused attempt writes no ledger row.
        ->and($wallet->transactions()->count())->toBe(0);
});

it('cannot freeze funds that are already frozen', function (): void {
    $wallet = makeWallet(5_000);
    $wallet->freeze(new Money(5_000));

    expect(fn () => $wallet->freeze(new Money(1)))->toThrow(InsufficientFunds::class);
});

it('returns frozen money to available on unfreeze', function (): void {
    $wallet = makeWallet(10_000);
    $wallet->freeze(new Money(4_000));

    $wallet->unfreeze(new Money(4_000));
    $wallet->refresh();

    expect($wallet->available_cents)->toBe(10_000)
        ->and($wallet->frozen_cents)->toBe(0);
});

it('takes charged money out of the wallet entirely', function (): void {
    $wallet = makeWallet(10_000);
    $wallet->freeze(new Money(4_000));

    $transaction = $wallet->charge(new Money(4_000));
    $wallet->refresh();

    expect($wallet->available_cents)->toBe(6_000)
        ->and($wallet->frozen_cents)->toBe(0)
        ->and($wallet->total()->cents)->toBe(6_000)
        ->and($transaction->amount_cents)->toBe(-4_000);
});

it('refuses to charge more than is frozen', function (): void {
    $wallet = makeWallet(10_000);
    $wallet->freeze(new Money(1_000));

    expect(fn () => $wallet->charge(new Money(2_000)))->toThrow(InsufficientFunds::class);
});

it('credits the available balance on refund', function (): void {
    $wallet = makeWallet(1_000);

    $wallet->refund(new Money(2_500));

    expect($wallet->fresh()->available_cents)->toBe(3_500);
});

it('rejects a zero or negative amount', function (): void {
    $wallet = makeWallet(10_000);

    expect(fn () => $wallet->deposit(new Money(0)))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $wallet->freeze(new Money(-100)))->toThrow(InvalidArgumentException::class);
});

it('rejects a currency the wallet does not hold', function (): void {
    $wallet = makeWallet(10_000);

    expect(fn () => $wallet->deposit(new Money(100, 'EUR')))->toThrow(InvalidArgumentException::class);
});

it('records balance_after on every row so the ledger reconstructs the balance', function (): void {
    $wallet = makeWallet();

    $wallet->deposit(new Money(10_000));
    $wallet->freeze(new Money(3_000));
    $wallet->unfreeze(new Money(1_000));
    $wallet->freeze(new Money(500));
    $wallet->charge(new Money(2_500));

    $wallet->refresh();
    $latest = $wallet->transactions()->first();

    expect($latest->balance_after_cents)->toBe($wallet->available_cents)
        ->and($latest->frozen_after_cents)->toBe($wallet->frozen_cents)
        ->and($wallet->transactions()->count())->toBe(5);
});

it('links a transaction to whatever caused it', function (): void {
    $wallet = makeWallet(10_000);
    $other = makeWallet(1);

    $transaction = $wallet->freeze(new Money(1_000), $other, 'Test reference');

    expect($transaction->reference_type)->toBe($other->getMorphClass())
        ->and($transaction->reference_id)->toBe($other->id)
        ->and($transaction->description)->toBe('Test reference');
});
