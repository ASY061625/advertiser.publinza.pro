<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Notifications\Enums\NotificationType;

/** What happened across every project last week. */
class WeeklySummaryNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $published,
        private readonly int $inProgress,
        private readonly int $spentCents,
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::WeeklySummary;
    }

    public function title(): string
    {
        return 'Your week on Publinza';
    }

    public function body(): string
    {
        if ($this->published === 0 && $this->inProgress === 0) {
            return 'Nothing moved last week. Your balance and your queue are where you left them.';
        }

        return sprintf(
            '%s published, %s still in progress, %s spent.',
            $this->published,
            $this->inProgress,
            (new Money($this->spentCents))->format(),
        );
    }

    public function href(): string
    {
        return '/dashboard';
    }

    protected function action(): string
    {
        return 'Open your dashboard';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'published' => $this->published,
            'in_progress' => $this->inProgress,
            'spent_cents' => $this->spentCents,
        ];
    }
}
