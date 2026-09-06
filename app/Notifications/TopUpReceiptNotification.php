<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Billing\DTOs\Money;
use App\Domain\Billing\Models\TopUp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We received your payment", by mail and in the app.
 *
 * A receipt, not a marketing note: the figure paid, the credit added if there
 * was one, and the balance it produced. Somebody reconciling a card statement
 * three weeks later needs those three numbers and the reference.
 *
 * Queued, so a slow mail server never sits between a payment succeeding and the
 * balance page saying so.
 */
class TopUpReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly TopUp $topUp) {}

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
            'type' => 'balance.topped_up',
            'top_up_id' => $this->topUp->id,
            'reference' => $this->topUp->reference,
            'amount_cents' => $this->topUp->amount_cents,
            'bonus_cents' => $this->topUp->bonus_cents,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = new Money($this->topUp->amount_cents);
        $bonus = new Money($this->topUp->bonus_cents);

        $mail = (new MailMessage)
            ->subject("Payment received — {$amount->format()}")
            ->line(sprintf(
                'We have added %s to your Publinza balance, paid by %s.',
                $amount->format(),
                mb_strtolower($this->topUp->method->label()),
            ));

        if ($bonus->isPositive()) {
            $mail->line(sprintf(
                'Your volume bonus of %s has been credited on top, so %s reached your balance.',
                $bonus->format(),
                $this->topUp->credited()->format(),
            ));
        }

        return $mail
            ->line("Reference {$this->topUp->reference}.")
            // Not "spent". The distinction is the one advertisers ask support
            // about most, so the receipt makes it before they have to ask.
            ->line('Your balance is money you hold with us. It is only spent when a placement goes live and its link is verified.')
            ->action('View your balance', $this->url());
    }

    /**
     * Built against the advertiser subdomain rather than `url()`, which
     * resolves against APP_URL — that is the marketing site.
     */
    private function url(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return sprintf('%s://%s/balance', $scheme, config('publinza.domains.app'));
    }
}
