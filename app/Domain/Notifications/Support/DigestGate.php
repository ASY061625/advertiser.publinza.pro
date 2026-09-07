<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\NotificationDigest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * At most one email per fifteen minutes per person per type.
 *
 * The rule is easy to state and easy to get wrong in a way nobody notices: a
 * plain throttle would satisfy it by *dropping* everything that arrived inside
 * the window, so five placements going live in one minute would be one email
 * about the first and silence about the other four.
 *
 * So this is two mechanisms, not one. `allow()` answers "may this be mailed
 * now"; when the answer is no it records what was suppressed. `SendPendingDigests`
 * comes back once the window has closed and sends one email naming all of it.
 * Nothing is dropped — it is either sent immediately or gathered into the next
 * summary.
 */
final class DigestGate
{
    /** The window, in minutes. Named here so the command and the tests agree. */
    public const WINDOW_MINUTES = 15;

    /**
     * May this notification be mailed right now?
     *
     * When it may not, `$notificationId` is added to the pending batch, which
     * is why this takes the id rather than being a pure predicate: answering
     * "no" and remembering why are the same decision, and splitting them is how
     * a caller ends up doing one without the other.
     */
    public function allow(User $user, NotificationType $type, string $notificationId): bool
    {
        /*
         * One row, locked, inside a transaction.
         *
         * Two notifications of the same type arriving in the same second is
         * exactly the case this exists for, and it is also exactly the case
         * where a read-then-write races: both read an expired window, both
         * decide to send, and the person gets the two emails the rule forbids.
         */
        return DB::transaction(function () use ($user, $type, $notificationId): bool {
            $digest = NotificationDigest::query()
                ->where('user_id', $user->id)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            if ($digest === null) {
                NotificationDigest::query()->create([
                    'user_id' => $user->id,
                    'type' => $type->value,
                    'last_sent_at' => now(),
                    'pending_count' => 0,
                    'pending_ids' => [],
                ]);

                return true;
            }

            $openedAt = $digest->last_sent_at?->addMinutes(self::WINDOW_MINUTES);

            if ($openedAt === null || $openedAt->isPast()) {
                $digest->forceFill([
                    'last_sent_at' => now(),
                    'pending_count' => 0,
                    'pending_ids' => [],
                ])->save();

                return true;
            }

            // Inside the window. Held for the flush, not thrown away.
            $ids = $digest->pending_ids ?? [];

            // Capped: a runaway loop must not grow one row without limit. The
            // count keeps counting past the cap, so the summary still says how
            // many even when it can only link to the first fifty.
            if (count($ids) < 50) {
                $ids[] = $notificationId;
            }

            $digest->forceFill([
                'pending_count' => $digest->pending_count + 1,
                'pending_ids' => $ids,
            ])->save();

            return false;
        });
    }

    /**
     * The digests whose window has closed with something waiting in them.
     *
     * @return Collection<int, NotificationDigest>
     */
    public function due(): Collection
    {
        return NotificationDigest::query()
            ->with('user')
            ->where('pending_count', '>', 0)
            ->where(function ($query): void {
                $query->whereNull('last_sent_at')
                    ->orWhere('last_sent_at', '<=', now()->subMinutes(self::WINDOW_MINUTES));
            })
            ->get();
    }

    /** Closes a batch after its summary has gone out. */
    public function markSent(NotificationDigest $digest): void
    {
        $digest->forceFill([
            'last_sent_at' => now(),
            'pending_count' => 0,
            'pending_ids' => [],
        ])->save();
    }
}
