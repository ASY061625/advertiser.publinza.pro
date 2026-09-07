<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Models\Post;

/** A publisher-written draft is waiting to be approved or sent back. */
class ArticleReadyForReviewNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $postId,
        private readonly string $domain,
        private readonly int $wordCount,
    ) {
        parent::__construct();
    }

    public static function for(Post $post, int $wordCount): self
    {
        return new self($post->id, $post->website?->domain ?? 'a site', $wordCount);
    }

    public function type(): NotificationType
    {
        return NotificationType::ArticleReadyForReview;
    }

    public function title(): string
    {
        return "Draft ready for {$this->domain}";
    }

    public function body(): string
    {
        return "{$this->wordCount} words written for you. Approve it or send it back with notes.";
    }

    public function href(): string
    {
        return "/posts/{$this->postId}?tab=article";
    }

    protected function action(): string
    {
        return 'Review the draft';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['post_id' => $this->postId, 'domain' => $this->domain, 'words' => $this->wordCount];
    }
}
