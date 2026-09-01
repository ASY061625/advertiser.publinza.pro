<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\Wallet;
use App\Exceptions\InsufficientFunds;
use Illuminate\Console\Command;

/**
 * Attempts one wallet freeze and reports whether it succeeded.
 *
 * This exists so the concurrency test can run genuinely parallel OS processes
 * against the same row — the only way to prove the `SELECT ... FOR UPDATE` lock
 * actually serialises contending writers. Hidden from `artisan list`; it is
 * test scaffolding, not an operator tool.
 */
class TryFreezeWallet extends Command
{
    protected $signature = 'wallet:try-freeze {wallet : Wallet id} {cents : Amount in cents}';

    protected $description = 'Attempt to freeze an amount on a wallet (test harness).';

    protected $hidden = true;

    public function handle(): int
    {
        $wallet = Wallet::query()->find((int) $this->argument('wallet'));

        if ($wallet === null) {
            $this->line('MISSING');

            return self::FAILURE;
        }

        try {
            $wallet->freeze(new Money((int) $this->argument('cents')));
            $this->line('FROZE');

            return self::SUCCESS;
        } catch (InsufficientFunds) {
            $this->line('REFUSED');

            // A refusal is the correct outcome, not a crash.
            return self::SUCCESS;
        }
    }
}
