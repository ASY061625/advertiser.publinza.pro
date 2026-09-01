<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = config('publinza.app_url').'/reset-password/'.$this->token.'?email='.urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Reset your Publinza password')
            ->greeting('Reset your password')
            ->line('Use the button below to choose a new password. The link works once, and expires in 60 minutes.')
            ->action('Choose a new password', $url)
            ->line('Setting a new password signs you out everywhere else, including any device you told us to trust.')
            ->line('If you did not ask for this, you can ignore it — your password has not changed.');
    }
}
