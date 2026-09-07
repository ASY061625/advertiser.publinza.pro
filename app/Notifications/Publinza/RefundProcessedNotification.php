<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Notifications\Enums\NotificationType;

/** Money came back. */
class RefundProcessedNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $amountCents,
        private readonly string $reason,
        private readonly ?int $postId = null,
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::RefundProcessed;
    }

    public function title(): string
    {
        return (new Money($this->amountCents))->format().' refunded';
    }

    public function body(): string
    {
        return "{$this->reason} It is back on your balance and ready to spend.";
    }

    public function href(): string
    {
        return $this->postId === null ? '/balance?tab=transactions' : "/posts/{$this->postId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['amount_cents' => $this->amountCents, 'post_id' => $this->postId];
    }
}
