<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Notifications\Enums\NotificationType;

/** The balance will not cover what is queued. */
class BalanceLowNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $availableCents,
        private readonly int $queuedCents,
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::BalanceLow;
    }

    public function title(): string
    {
        return 'Your balance is running low';
    }

    public function body(): string
    {
        $short = max(0, $this->queuedCents - $this->availableCents);

        // The number that matters is the shortfall, not the balance: "you have
        // $180" leaves the arithmetic to the reader.
        return sprintf(
            '%s available against %s of queued orders — %s short.',
            (new Money($this->availableCents))->format(),
            (new Money($this->queuedCents))->format(),
            (new Money($short))->format(),
        );
    }

    public function href(): string
    {
        return '/balance?tab=top-up';
    }

    protected function action(): string
    {
        return 'Top up';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['available_cents' => $this->availableCents, 'queued_cents' => $this->queuedCents];
    }
}
