<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Billing\Actions\ReleaseFundsToPublisher;
use App\Domain\Posts\DTOs\PostStatus;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Facades\DB;

final class MarkPostPublished
{
    public function __construct(private readonly ReleaseFundsToPublisher $releaseFunds) {}

    public function handle(Post $post, string $publishedUrl): Post
    {
        return DB::transaction(function () use ($post, $publishedUrl): Post {
            $post->update([
                'status' => PostStatus::Published->value,
                'published_url' => $publishedUrl,
                'published_at' => now(),
            ]);

            // Funds are frozen at checkout and only released once the URL is live.
            $this->releaseFunds->handle($post);

            return $post->refresh();
        });
    }
}
