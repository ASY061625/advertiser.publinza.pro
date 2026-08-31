<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\DTOs\PostStatus;
use App\Domain\Posts\Models\Post;
use RuntimeException;

final class ApproveDraft
{
    /** The advertiser signs off the writer's draft; the publisher then posts it. */
    public function handle(Post $post): Post
    {
        if ($post->status !== PostStatus::ContentReview->value) {
            throw new RuntimeException('Only a post in content review can be approved.');
        }

        $post->update(['status' => PostStatus::InProgress->value]);

        return $post->refresh();
    }
}
