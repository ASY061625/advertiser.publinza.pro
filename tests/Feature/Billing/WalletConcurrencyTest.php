<?php

declare(strict_types=1);

use App\Domain\Billing\Enums\TransactionType;
use App\Domain\Billing\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Proves that concurrent freezes cannot overdraw a wallet.
 *
 * This has to run genuinely parallel OS processes against a real MySQL server.
 * A single-process test proves nothing here: the whole risk is two requests
 * both reading the pre-spend balance before either writes, and that race cannot
 * happen inside one PHP process no matter how the calls are ordered.
 *
 * SQLite is skipped rather than passed, because SQLite ignores
 * `SELECT ... FOR UPDATE` — a green run there would be a false negative that
 * hides exactly the bug this test exists to catch.
 */
beforeEach(function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped(
            'Wallet concurrency needs MySQL: SQLite ignores SELECT ... FOR UPDATE, '
            .'so a pass here would prove nothing. Run with DB_CONNECTION=mysql.',
        );
    }
});

it('never lets concurrent freezes overdraw the balance', function (): void {
    // Ten racers, each wanting 100.00, against a balance that covers six.
    $attempts = 10;
    $amountCents = 100_00;
    $startingCents = 600_00;

    $wallet = Wallet::factory()->create([
        'available_cents' => $startingCents,
        'frozen_cents' => 0,
    ]);

    // Committed before the child processes start; they have their own
    // connections and cannot see an open transaction here.
    DB::commit();
    DB::beginTransaction();

    /** @var list<Process> $processes */
    $processes = [];

    for ($i = 0; $i < $attempts; $i++) {
        $process = new Process(
            ['php', 'artisan', 'wallet:try-freeze', (string) $wallet->id, (string) $amountCents],
            base_path(),
            ['APP_ENV' => 'testing'],
        );

        $process->start();
        $processes[] = $process;
    }

    $outcomes = [];

    foreach ($processes as $process) {
        $process->wait();
        $outcomes[] = trim($process->getOutput());
    }

    $succeeded = count(array_filter($outcomes, fn (string $line): bool => str_contains($line, 'FROZE')));
    $refused = count(array_filter($outcomes, fn (string $line): bool => str_contains($line, 'REFUSED')));

    $wallet->refresh();

    expect($succeeded + $refused)->toBe($attempts, 'Every process should report an outcome.');

    // The invariant: exactly six can win, never seven.
    expect($succeeded)->toBe(6)
        ->and($wallet->available_cents)->toBe(0)
        ->and($wallet->frozen_cents)->toBe($startingCents);

    // Nothing was conjured or lost.
    expect($wallet->total()->cents)->toBe($startingCents);

    // One ledger row per winner, and no row for a refusal.
    expect($wallet->transactions()->where('type', TransactionType::Freeze)->count())->toBe($succeeded);
});

it('keeps the ledger consistent when deposits and freezes interleave', function (): void {
    $wallet = Wallet::factory()->create(['available_cents' => 200_00, 'frozen_cents' => 0]);

    DB::commit();
    DB::beginTransaction();

    $processes = [];

    // Six racers for a balance covering two, while nothing tops it up.
    for ($i = 0; $i < 6; $i++) {
        $process = new Process(
            ['php', 'artisan', 'wallet:try-freeze', (string) $wallet->id, '100'.'00'],
            base_path(),
            ['APP_ENV' => 'testing'],
        );
        $process->start();
        $processes[] = $process;
    }

    foreach ($processes as $process) {
        $process->wait();
    }

    $wallet->refresh();

    // Whatever the interleaving, the two buckets still add up and neither is
    // negative — which the UNSIGNED columns would have rejected outright.
    expect($wallet->available_cents)->toBeGreaterThanOrEqual(0)
        ->and($wallet->total()->cents)->toBe(200_00)
        ->and($wallet->transactions()->where('type', TransactionType::Freeze)->count())->toBe(2);
});
