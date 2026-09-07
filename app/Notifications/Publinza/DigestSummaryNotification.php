<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The catch-up email for everything one window suppressed.
 *
 * Deliberately *not* a PublinzaNotification: it writes no database row and
 * asks the digest gate nothing. The in-app records for these already exist —
 * they were written when each notification arrived, and the drawer has been
 * showing them all along. This is only the email that would otherwise have
 * been fifteen separate ones.
 *
 * @param  list<array{title: string, body: string, href: string}>  $items
 */
class DigestSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{title: string, body: string, href: string}>  $items
     */
    public function __construct(
        private readonly NotificationType $type,
        private readonly array $items,
        private readonly int $total,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->type->summary($this->total))
            ->line("While you were away, {$this->type->summary($this->total)}:");

        foreach ($this->items as $item) {
            // The title carries the detail — "Published on techweekly.com" —
            // so a list of them reads as a summary without a paragraph each.
            $mail->line("• {$item['title']} — {$item['body']}");
        }

        if ($this->total > count($this->items)) {
            $remaining = $this->total - count($this->items);

            $mail->line("…and {$remaining} more.");
        }

        return $mail
            ->action('See them all', $this->url())
            ->line('You get at most one of these every fifteen minutes per kind of update.');
    }

    private function url(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return sprintf('%s://%s/notifications', $scheme, config('publinza.domains.app'));
    }
}
