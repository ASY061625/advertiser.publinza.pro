<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Billing\Actions\SettlePostFunds;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\DB;

/** The publisher turns the placement down; the advertiser gets their money back. */
final class RejectPost
{
    public function __construct(private readonly SettlePostFunds $settle) {}

    public function handle(Post $post, string $reason): Post
    {
        return DB::transaction(function () use ($post, $reason): Post {
            $held = $post->status->holdsFrozenFunds();

            $post->transitionTo(PostStatus::Rejected, $reason, ['rejection_reason' => $reason]);

            if ($held) {
                $this->settle->returnToAdvertiser($post, 'rejected');
            }

            return $post;
        });
    }
}
