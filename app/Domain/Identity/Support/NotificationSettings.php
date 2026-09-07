<?php

declare(strict_types=1);

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Models\NotificationPreference;
use App\Models\User;

/**
 * The one place that answers "should this person hear about this, and how".
 *
 * Everything that sends anything asks here rather than reading a column, so
 * there is a single set of rules: the stored preference, the event's default
 * where nothing is stored, the pause window, and the transactional override.
 * Two systems answering that question is how somebody ends up muted from a
 * refund notice.
 */
final class NotificationSettings
{
    public function wants(User $user, NotificationEvent $event, NotificationChannel $channel): bool
    {
        /*
         * Money and orders always get through, on email.
         *
         * Checked before the stored preference rather than after, because the
         * switch for these is shown locked — and a row written before that lock
         * existed must not be able to silence a refund.
         */
        if ($event->isTransactional() && $channel === NotificationChannel::Email) {
            return true;
        }

        if ($user->notificationsArePaused()) {
            return false;
        }

        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event', $event->value)
            ->first();

        if ($stored === null) {
            return $event->defaults()[$channel->column()];
        }

        return (bool) $stored->{$channel->column()};
    }

    /**
     * The whole matrix, for the screen that edits it.
     *
     * @return array<string, array{email: bool, in_app: bool, push: bool}>
     */
    public function matrix(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (NotificationPreference $row): string => $row->event->value);

        $matrix = [];

        foreach (NotificationEvent::cases() as $event) {
            $row = $stored->get($event->value);

            $matrix[$event->value] = $row === null
                ? $event->defaults()
                : ['email' => $row->email, 'in_app' => $row->in_app, 'push' => $row->push];

            // Shown as on and locked, whatever is stored underneath.
            if ($event->isTransactional()) {
                $matrix[$event->value]['email'] = true;
            }
        }

        return $matrix;
    }

    /**
     * @param  array<string, array<string, bool>>  $matrix
     */
    public function save(User $user, array $matrix): void
    {
        foreach (NotificationEvent::cases() as $event) {
            $row = $matrix[$event->value] ?? null;

            if ($row === null) {
                continue;
            }

            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id, 'event' => $event->value],
                [
                    // Stored as sent, but `wants()` overrides it for the
                    // transactional ones — so a future change of heart about
                    // which events are transactional does not silently inherit
                    // a false somebody could never have set.
                    'email' => (bool) ($row['email'] ?? false),
                    'in_app' => (bool) ($row['in_app'] ?? false),
                    'push' => (bool) ($row['push'] ?? false),
                ],
            );
        }
    }

    /**
     * The catalogue the screen renders, with each event's copy and lock state.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        return array_map(static fn (NotificationEvent $event): array => [
            'value' => $event->value,
            'label' => $event->label(),
            'description' => $event->description(),
            'transactional' => $event->isTransactional(),
        ], NotificationEvent::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function channels(): array
    {
        return array_map(static fn (NotificationChannel $channel): array => [
            'value' => $channel->value,
            'label' => $channel->label(),
        ], NotificationChannel::cases());
    }
}
