<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Replaces Laravel's default so the copy is in the product's voice, and so the
 * signed URL is built against the advertiser subdomain rather than APP_URL,
 * which points at the marketing site.
 */
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email address')
            ->greeting('Welcome to Publinza')
            ->line('Confirm this address and your account is ready to use.')
            ->action('Confirm email address', $this->verificationUrl($notifiable))
            ->line('The link works for 60 minutes. If it expires, sign in and ask for another.')
            ->line('If you did not create a Publinza account, ignore this message and nothing will happen.');
    }
}
