<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * "Publinza replied", by mail and in the app.
 *
 * Whether this is sent at all is decided before it is constructed — see
 * PostMessage, which checks the account preference and the thread's own mute.
 * Putting the check there rather than in via() means one place answers "should
 * this person be emailed", and the database record is not written for a
 * notification the mail channel then silently drops.
 *
 * Queued, so a slow mail server never sits between a teammate pressing send and
 * the advertiser's browser receiving the message.
 */
class TeamReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const EXCERPT = 140;

    public function __construct(
        private readonly Conversation $conversation,
        private readonly Message $message,
    ) {}

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
            'type' => 'conversation.reply',
            'conversation_id' => $this->conversation->id,
            'subject' => $this->conversation->subject,
            'excerpt' => $this->excerpt(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->conversation->website?->domain !== null
            ? "Publinza replied about {$this->conversation->website->domain}"
            : 'Publinza replied to your message';

        return (new MailMessage)
            ->subject($subject)
            ->line("Re: {$this->conversation->subject}")
            // The reply itself, not just a notice that one exists. Most of
            // these are one line — "published this morning, link below" — and
            // an email that makes somebody sign in to read eight words is an
            // email that trains them to ignore the next one.
            ->line($this->excerpt())
            ->action('Reply in Publinza', $this->url())
            ->line('You can turn these emails off in your notification settings, or mute this one conversation from its menu.');
    }

    private function excerpt(): string
    {
        return Str::limit(strip_tags($this->message->body), self::EXCERPT);
    }

    /**
     * Built against the advertiser subdomain rather than `url()`, which
     * resolves against APP_URL — that is the marketing site, and the thread
     * does not exist there.
     */
    private function url(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return sprintf(
            '%s://%s/conversations?thread=%d',
            $scheme,
            config('publinza.domains.app'),
            $this->conversation->id,
        );
    }
}
