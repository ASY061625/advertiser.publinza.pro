<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Billing\Actions\SettlePostFunds;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a pre-posted placement and hands the money back, then records the
 * refund by moving the post to `refunded`.
 */
final class CancelPost
{
    public function __construct(private readonly SettlePostFunds $settle) {}

    public function handle(Post $post, string $reason): Post
    {
        return DB::transaction(function () use ($post, $reason): Post {
            $held = $post->status->holdsFrozenFunds();

            $post->transitionTo(PostStatus::Cancelled, $reason);

            // A draft never reached checkout, so there is nothing frozen to return.
            if ($held) {
                $this->settle->returnToAdvertiser($post, 'cancelled');
                $post->transitionTo(PostStatus::Refunded, $reason);
            }

            return $post;
        });
    }
}
