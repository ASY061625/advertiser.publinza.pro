<?php

declare(strict_types=1);

namespace App\Notifications\Publinza;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Support\DigestGate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * What every Publinza notification has in common.
 *
 * One subclass per type — fifteen of them — each supplying a title, a one-line
 * body, a deep link and whatever payload its template needs. Everything else
 * (which channels, the preference check, the digest window, the shape of the
 * stored row) is decided once, here, because fifteen copies of a delivery rule
 * is fifteen chances for one of them to be subtly different.
 *
 * The three channels:
 *
 *   database  — always. The record is the notification; the drawer reads it and
 *               it stays there whatever the preferences say. Somebody who has
 *               muted a type still gets to find out what happened by looking.
 *   broadcast — gated on the in-app preference. This is the interruption: the
 *               badge moving in an open tab, and the browser notification.
 *               Turning in-app off means "keep it, do not tap me on the
 *               shoulder about it".
 *   mail      — gated on the email preference *and* the fifteen-minute digest
 *               window. See DigestGate for why those are two separate checks.
 */
abstract class PublinzaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        /*
         * Assigned here rather than left to the framework.
         *
         * Laravel mints the id inside sendNow(), after via() has already run,
         * and only if the notification does not already carry one. Minting it
         * up front means the id is the same value in via(), shouldSend(),
         * toArray() and the digest batch — one identifier for one notification,
         * rather than one that only exists from halfway through.
         */
        $this->id = (string) Str::uuid();
    }

    abstract public function type(): NotificationType;

    /** The headline. Plain language, no jargon, no "Notification:" prefix. */
    abstract public function title(): string;

    /** One line under it. Says what changed and about what. */
    abstract public function body(): string;

    /** Where clicking it goes. Always a path within the advertiser app. */
    abstract public function href(): string;

    /**
     * Anything else the stored row should carry.
     *
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [];
    }

    /**
     * The email subject, where it should differ from the title.
     *
     * Most do not: an email whose subject line disagrees with the notification
     * it is about makes two things out of one.
     */
    protected function subject(): string
    {
        return $this->title();
    }

    /** The button on the email. */
    protected function action(): string
    {
        return 'Open in Publinza';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! $notifiable instanceof User) {
            return $channels;
        }

        $settings = app(NotificationSettings::class);
        $event = $this->type()->preference();

        if ($settings->wants($notifiable, $event, NotificationChannel::InApp)) {
            $channels[] = 'broadcast';
        }

        if ($settings->wants($notifiable, $event, NotificationChannel::Email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * The digest window, checked at send time rather than at queue time.
     *
     * via() decides whether this person wants the email at all; this decides
     * whether the window is open. Splitting them means a preference change is
     * cheap to answer and the throttle is evaluated against the clock at the
     * moment the mail would actually go out, not when it was queued.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel !== 'mail' || ! $notifiable instanceof User) {
            return true;
        }

        return app(DigestGate::class)->allow($notifiable, $this->type(), (string) $this->id);
    }

    /**
     * The stored row, and the broadcast payload.
     *
     * Rendered here rather than in the client: these rows are read back months
     * later, and a title assembled in React from ids that have since been
     * deleted is a notification that reads "published on undefined". The row
     * carries the finished sentence.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            'title' => $this->title(),
            'body' => $this->body(),
            'href' => $this->href(),
            'icon' => $this->type()->icon(),
            'tone' => $this->type()->tone(),
            'collapsible' => $this->type()->collapsible(),
        ] + $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $payload = $this->toArray($notifiable);

        /*
         * Whether the client may raise a browser notification for this one.
         *
         * Decided on the server, with the preference in front of it, rather
         * than left to the browser: the browser only knows whether permission
         * was granted, which is a different question from whether this person
         * asked to be interrupted about *this*.
         */
        $payload['push'] = $notifiable instanceof User
            && app(NotificationSettings::class)->wants(
                $notifiable,
                $this->type()->preference(),
                NotificationChannel::Push,
            );

        return new BroadcastMessage($payload);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->line($this->body())
            ->action($this->action(), $this->url($this->href()));
    }

    /**
     * An absolute URL on the advertiser subdomain.
     *
     * Not `url()`, which resolves against APP_URL — that is the marketing site,
     * and none of these pages exist there.
     */
    protected function url(string $path): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return sprintf('%s://%s%s', $scheme, config('publinza.domains.app'), $path);
    }
}
