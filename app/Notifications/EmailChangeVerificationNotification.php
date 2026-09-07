<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Identity\Actions\RequestEmailChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Confirm your new address", sent to the address being proven.
 *
 * The routing override below is the whole mechanism: the point of this message
 * is that the *new* inbox proves it can receive mail, so sending it to the
 * address already on file would confirm nothing and change the account on the
 * say-so of whoever holds the session.
 *
 * Notifications route by `routeNotificationFor`, which Laravel looks for on
 * the *notification* before falling back to the notifiable's own. A `to()` on
 * the MailMessage does not exist and would have silently addressed this to the
 * old inbox.
 */
class EmailChangeVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $newEmail,
        private readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Where this one goes, whatever address the account currently holds. */
    public function routeNotificationForMail(object $notifiable): string
    {
        return $this->newEmail;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your new email address')
            ->line('Somebody asked to move a Publinza account to this address.')
            ->action('Confirm this address', $this->url())
            ->line(sprintf(
                'The link works for %d hours. Until you use it, the account keeps its old address — nothing has changed yet.',
                RequestEmailChange::TTL_HOURS,
            ))
            ->line('If this was not you, ignore this message. Without this confirmation the change never happens.');
    }

    /**
     * Built against the advertiser subdomain rather than `url()`, which
     * resolves against APP_URL — that is the marketing site.
     */
    private function url(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return sprintf(
            '%s://%s/profile/email/%s',
            $scheme,
            config('publinza.domains.app'),
            $this->token,
        );
    }
}
