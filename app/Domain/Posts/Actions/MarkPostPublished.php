<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;

/**
 * Records the live URL and opens the 3-day verification window. Funds stay
 * frozen until CompletePost closes it.
 */
final class MarkPostPublished
{
    public function handle(Post $post, string $publishedUrl): Post
    {
        return $post->transitionTo(PostStatus::Posted, null, [
            'published_url' => $publishedUrl,
            'published_at' => now(),
            'frozen_until' => now()->addDays(3),
        ]);
    }
}
