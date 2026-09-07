<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Str;

/** A publisher turned a placement down, and why. */
class PostRejectedNotification extends PublinzaNotification
{
    private const REASON_LIMIT = 120;

    public function __construct(
        private readonly int $postId,
        private readonly string $domain,
        private readonly ?string $reason,
    ) {
        parent::__construct();
    }

    public static function for(Post $post): self
    {
        return new self($post->id, $post->website?->domain ?? 'a site', $post->rejection_reason);
    }

    public function type(): NotificationType
    {
        return NotificationType::PostRejected;
    }

    public function title(): string
    {
        return "{$this->domain} turned the post down";
    }

    public function body(): string
    {
        // The reason, not a pointer to it: "see the post for details" is the
        // sentence that makes somebody open a tab to read one line.
        return $this->reason === null || trim($this->reason) === ''
            ? 'No reason was given. The money is back on your balance.'
            : Str::limit(trim($this->reason), self::REASON_LIMIT);
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
        return ['post_id' => $this->postId, 'domain' => $this->domain];
    }
}
