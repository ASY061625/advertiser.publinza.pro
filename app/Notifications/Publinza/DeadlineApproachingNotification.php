<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Models\Post;

/** A post is due in the next few days and is not published yet. */
class DeadlineApproachingNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $postId,
        private readonly string $domain,
        private readonly int $daysLeft,
    ) {
        parent::__construct();
    }

    public static function for(Post $post, int $daysLeft): self
    {
        return new self($post->id, $post->website?->domain ?? 'a site', $daysLeft);
    }

    public function type(): NotificationType
    {
        return NotificationType::DeadlineApproaching;
    }

    public function title(): string
    {
        return match (true) {
            $this->daysLeft <= 0 => "{$this->domain} is due today",
            $this->daysLeft === 1 => "{$this->domain} is due tomorrow",
            default => "{$this->domain} is due in {$this->daysLeft} days",
        };
    }

    public function body(): string
    {
        return 'The publisher has not posted it yet. Message us if it needs chasing.';
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
        return ['post_id' => $this->postId, 'domain' => $this->domain, 'days_left' => $this->daysLeft];
    }
}
