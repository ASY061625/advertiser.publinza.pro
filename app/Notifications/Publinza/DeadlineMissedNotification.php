<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Models\Post;

/** The date passed and the placement is still not live. */
class DeadlineMissedNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $postId,
        private readonly string $domain,
        private readonly int $daysLate,
    ) {
        parent::__construct();
    }

    public static function for(Post $post, int $daysLate): self
    {
        return new self($post->id, $post->website?->domain ?? 'a site', $daysLate);
    }

    public function type(): NotificationType
    {
        return NotificationType::DeadlineMissed;
    }

    public function title(): string
    {
        return "{$this->domain} missed its deadline";
    }

    public function body(): string
    {
        $late = $this->daysLate === 1 ? '1 day' : "{$this->daysLate} days";

        // Says what we are doing about it. A notice that only reports a problem
        // reads as a shrug, and this is one we caused.
        return "It is {$late} late. We are chasing the publisher — your money stays frozen until it lands.";
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
        return ['post_id' => $this->postId, 'domain' => $this->domain, 'days_late' => $this->daysLate];
    }
}
