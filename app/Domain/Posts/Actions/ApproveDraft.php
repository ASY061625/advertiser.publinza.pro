<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;

/**
 * The advertiser signs off the writer's draft and the publisher goes on to post
 * it. The illegal-move check lives in the observer, so this does not repeat it.
 */
final class ApproveDraft
{
    public function handle(Post $post, ?string $note = null): Post
    {
        $post->article?->update(['approved_at' => now()]);

        return $post->transitionTo(PostStatus::Posted, $note);
    }
}
