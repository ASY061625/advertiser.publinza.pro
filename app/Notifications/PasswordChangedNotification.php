<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your password was changed."
 *
 * Sent to the address on file, always, and not switchable off. If the person
 * who changed it was not the owner, this mail is the only warning the owner
 * gets — which is exactly when a notification preference must not apply.
 */
class PasswordChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?string $ip,
        private readonly int $sessionsSignedOut,
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
            'type' => 'security.password_changed',
            'ip' => $this->ip,
            'sessions_signed_out' => $this->sessionsSignedOut,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your Publinza password was changed')
            ->line('The password on your Publinza account was changed just now.')
            ->line($this->ip === null ? 'We could not record where from.' : "The request came from {$this->ip}.");

        if ($this->sessionsSignedOut > 0) {
            $mail->line(sprintf(
                'Every other signed-in browser was signed out — %d %s ended.',
                $this->sessionsSignedOut,
                $this->sessionsSignedOut === 1 ? 'session was' : 'sessions were',
            ));
        }

        return $mail
            // The action people need if this was not them, in the order they
            // need it: get back in first, then look at what happened.
            ->action('Reset your password', $this->resetUrl())
            ->line('If this was not you, reset your password now and then check your active sessions and API tokens.');
    }

    private function resetUrl(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return sprintf('%s://%s/forgot-password', $scheme, config('publinza.domains.app'));
    }
}
