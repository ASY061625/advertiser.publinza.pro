<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Trading\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your order is placed", by mail and in the app.
 *
 * Both channels lead with the thing advertisers ask support about first: the
 * money is frozen, not spent. A marketplace that takes several hundred dollars
 * and says nothing about when it leaves the account generates a support ticket
 * per order.
 *
 * Queued, so the mail server is not in the checkout's critical path.
 */
class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order.placed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'total_cents' => $this->order->total_cents,
            'post_count' => $this->order->posts()->count(),
            'drafts' => $this->order->posts()->where('status', PostStatus::Draft)->count(),
            'url' => url("/checkout/{$this->order->order_number}"),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $drafts = $this->order->posts()->where('status', PostStatus::Draft)->count();

        $mail = (new MailMessage)
            ->subject("Order {$this->order->order_number} placed")
            ->line(sprintf(
                'We have placed %s across %d %s.',
                (new Money($this->order->total_cents, $this->order->currency))->format(),
                $this->order->posts()->count(),
                $this->order->posts()->count() === 1 ? 'site' : 'sites',
            ))
            ->line('That amount is frozen in your balance, not spent. Each site is paid only once its link has been verified as live — if a placement falls through, the money comes straight back to you.');

        if ($drafts > 0) {
            $mail->line(sprintf(
                '%d %s waiting on your article. %s stay as drafts until you submit the copy.',
                $drafts,
                $drafts === 1 ? 'placement is' : 'placements are',
                $drafts === 1 ? 'It stays a draft' : 'They stay drafts',
            ));
        }

        return $mail->action('View your posts', url('/posts'));
    }
}
