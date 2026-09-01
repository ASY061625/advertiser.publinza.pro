<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Billing\Actions\SettlePostFunds;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\DB;

/**
 * Closes the verification window: the post is confirmed live and the frozen
 * funds become platform revenue.
 */
final class CompletePost
{
    public function __construct(private readonly SettlePostFunds $settle) {}

    public function handle(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $post->transitionTo(PostStatus::Completed);
            $this->settle->releaseToPlatform($post);

            return $post;
        });
    }
}
