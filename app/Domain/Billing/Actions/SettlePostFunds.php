<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\Wallet;
use App\Domain\Posts\Models\Post;

/**
 * Moves a post's money once its outcome is known.
 *
 * `completed` charges the frozen amount out of the wallet: it has become
 * platform revenue.
 *
 * `cancelled` and `rejected` return it to the advertiser. That is a single
 * `unfreeze` — frozen → available — and deliberately not an unfreeze followed
 * by a `refund`. Both of those methods credit the available balance, so calling
 * them in sequence would hand the advertiser the money twice. The ledger still
 * shows the event: the unfreeze row carries the post reference and the reason.
 *
 * Wallet::refund() exists for genuine out-of-band credits — a goodwill payment,
 * or reversing a charge that already left the wallet — where nothing is frozen
 * to release.
 */
final class SettlePostFunds
{
    public function releaseToPlatform(Post $post): void
    {
        $this->walletFor($post)?->charge($post->price(), $post, "Post #{$post->id} completed");
    }

    public function returnToAdvertiser(Post $post, string $reason): void
    {
        $this->walletFor($post)?->unfreeze($post->price(), $post, "Post #{$post->id} {$reason}");
    }

    private function walletFor(Post $post): ?Wallet
    {
        return Wallet::query()->firstWhere('user_id', $post->user_id);
    }
}
