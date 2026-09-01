<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent once when repeated failed sign-ins lock an account.
 *
 * This is the one message that tells someone their account is under attack, so
 * it says plainly what happened, what we did, and what they should do — and
 * never includes a one-click "this was me" link, which would hand an attacker
 * the unlock.
 */
class AccountLockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private readonly int $minutes,
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
        return (new MailMessage)
            ->subject('Your Publinza account is locked for '.$this->minutes.' minutes')
            ->greeting('Someone could not sign in to your account')
            ->line('There were five failed sign-in attempts on your Publinza account, so we locked it for '
                .$this->minutes.' minutes. Nobody got in.')
            ->line('Attempt details:')
            ->line('IP address: '.$this->ipAddress)
            ->line('Browser: '.$this->userAgent)
            ->line('If that was you, wait '.$this->minutes.' minutes and try again, or reset your password now.')
            ->action('Reset your password', config('publinza.app_url').'/forgot-password')
            ->line('If it was not you, reset your password and turn on two-factor authentication. '
                .'We will never ask you for your password or a two-factor code by email.');
    }
}
