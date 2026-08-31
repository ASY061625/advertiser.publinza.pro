<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Transaction;
use App\Domain\Billing\Models\Wallet;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\DB;

final class ReleaseFundsToPublisher
{
    /** Called when a post is confirmed published; unfreezes and debits. */
    public function handle(Post $post): void
    {
        DB::transaction(function () use ($post): void {
            /** @var Wallet|null $wallet */
            $wallet = Wallet::query()->lockForUpdate()->firstWhere('user_id', $post->project->user_id);

            if ($wallet === null) {
                return;
            }

            $price = (int) $post->site->price_minor_units;

            $wallet->decrement('frozen_minor_units', $price);
            $wallet->decrement('balance_minor_units', $price);

            Transaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'charge',
                'amount_minor_units' => -$price,
                'reference_type' => 'post',
                'reference_id' => (string) $post->id,
            ]);
        });
    }
}
