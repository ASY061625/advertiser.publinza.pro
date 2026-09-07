<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Models\Post;

/** The article cleared review and is queued for publication. */
class ArticleApprovedNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $postId,
        private readonly string $domain,
        private readonly ?string $expectedAt,
    ) {
        parent::__construct();
    }

    public static function for(Post $post): self
    {
        return new self(
            $post->id,
            $post->website?->domain ?? 'a site',
            $post->deadline_at?->toIso8601String(),
        );
    }

    public function type(): NotificationType
    {
        return NotificationType::ArticleApproved;
    }

    public function title(): string
    {
        return "Article approved for {$this->domain}";
    }

    public function body(): string
    {
        return 'It is with the publisher now. We will tell you the moment it goes live.';
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
        return ['post_id' => $this->postId, 'domain' => $this->domain, 'expected_at' => $this->expectedAt];
    }
}
