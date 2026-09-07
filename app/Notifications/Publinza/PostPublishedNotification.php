<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Models\Post;

/** A placement went live and its link was verified. */
class PostPublishedNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $postId,
        private readonly string $domain,
        private readonly ?string $publishedUrl,
    ) {
        parent::__construct();
    }

    public static function for(Post $post): self
    {
        return new self($post->id, $post->website?->domain ?? 'a site', $post->published_url);
    }

    public function type(): NotificationType
    {
        return NotificationType::PostPublished;
    }

    public function title(): string
    {
        return "Published on {$this->domain}";
    }

    public function body(): string
    {
        return $this->publishedUrl === null
            ? 'Your placement is live. The link is on the post.'
            : "Live at {$this->publishedUrl}";
    }

    public function href(): string
    {
        return "/posts/{$this->postId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['post_id' => $this->postId, 'domain' => $this->domain, 'published_url' => $this->publishedUrl];
    }
}
