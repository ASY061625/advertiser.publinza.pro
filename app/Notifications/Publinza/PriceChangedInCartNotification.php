<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Notifications\Enums\NotificationType;

/** A site in the cart moved price before checkout. */
class PriceChangedInCartNotification extends PublinzaNotification
{
    public function __construct(
        private readonly string $domain,
        private readonly int $fromCents,
        private readonly int $toCents,
    ) {
        parent::__construct();
    }

    public function type(): NotificationType
    {
        return NotificationType::PriceChangedInCart;
    }

    public function title(): string
    {
        $direction = $this->toCents > $this->fromCents ? 'went up' : 'came down';

        return "{$this->domain} {$direction}";
    }

    public function body(): string
    {
        return sprintf(
            'Now %s, was %s. Your cart shows the new price — nothing has been charged.',
            (new Money($this->toCents))->format(),
            (new Money($this->fromCents))->format(),
        );
    }

    public function href(): string
    {
        return '/cart';
    }

    protected function action(): string
    {
        return 'Open your cart';
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return ['domain' => $this->domain, 'from_cents' => $this->fromCents, 'to_cents' => $this->toCents];
    }
}
