<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Trading\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;

/** An order was placed and the money was frozen against it. */
class OrderConfirmedNotification extends PublinzaNotification
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $orderNumber,
        private readonly int $totalCents,
        private readonly int $placements,
        private readonly int $drafts = 0,
    ) {
        parent::__construct();
    }

    public static function for(Order $order, int $placements, int $drafts = 0): self
    {
        return new self($order->id, $order->order_number, $order->total_cents, $placements, $drafts);
    }

    public function type(): NotificationType
    {
        return NotificationType::OrderConfirmed;
    }

    public function title(): string
    {
        return "Order {$this->orderNumber} confirmed";
    }

    public function body(): string
    {
        $lines = $this->placements === 1 ? '1 placement' : "{$this->placements} placements";

        // "Frozen", not "charged": the distinction is the whole basis of the
        // refund promise, and this is the message where somebody first meets it.
        return sprintf(
            '%s for %s. The money is frozen, not spent — it comes back if a placement falls through.',
            $lines,
            (new Money($this->totalCents))->format(),
        );
    }

    public function href(): string
    {
        return "/checkout/{$this->orderId}";
    }

    protected function action(): string
    {
        return 'See the order';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Order {$this->orderNumber} placed")
            ->line(sprintf(
                'We have placed %s across %d %s.',
                (new Money($this->totalCents))->format(),
                $this->placements,
                $this->placements === 1 ? 'site' : 'sites',
            ))
            ->line('That amount is frozen in your balance, not spent. Each site is paid only once its link has been verified as live — if a placement falls through, the money comes straight back to you.');

        if ($this->drafts > 0) {
            $mail->line(sprintf(
                '%d %s waiting on your article. %s stay as drafts until you submit the copy.',
                $this->drafts,
                $this->drafts === 1 ? 'placement is' : 'placements are',
                $this->drafts === 1 ? 'It stays a draft' : 'They stay drafts',
            ));
        }

        return $mail->action('View your posts', $this->url('/posts'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'order_id' => $this->orderId,
            'drafts' => $this->drafts,
            'order_number' => $this->orderNumber,
            'total_cents' => $this->totalCents,
            'placements' => $this->placements,
        ];
    }
}
