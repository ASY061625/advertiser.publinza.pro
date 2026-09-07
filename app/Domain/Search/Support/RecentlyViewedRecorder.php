<?php

declare(strict_types=1);

namespace App\Domain\Search\Support;

use App\Domain\Search\Models\RecentlyViewed;
use App\Models\User;
use Throwable;

/**
 * Remembers what somebody opened, so the palette has something to show before
 * anything is typed.
 *
 * Two kinds only — websites and projects — because those are the two things
 * people come back to. A log of every post ever opened would bury them.
 */
final class RecentlyViewedRecorder
{
    public const WEBSITE = 'website';

    public const PROJECT = 'project';

    /** How many of each kind the palette offers. */
    public const KEEP = 5;

    /**
     * Updated in place, not appended.
     *
     * Somebody who opens the same site nine times in a morning has looked at
     * one site; a row per view would push everything else out of their five
     * most recent.
     */
    public function record(?User $user, string $type, int $id): void
    {
        if ($user === null) {
            return;
        }

        try {
            RecentlyViewed::query()->updateOrCreate(
                ['user_id' => $user->id, 'viewable_type' => $type, 'viewable_id' => $id],
                ['viewed_at' => now()],
            );
        } catch (Throwable) {
            /*
             * Never in the way of the page.
             *
             * This runs on every website and project open. A unique-index race
             * between two tabs, or a database hiccup, must not turn viewing a
             * site into an error — the cost of losing one row here is that the
             * palette's "recent" list is one entry out of date.
             */
        }
    }

    /**
     * The ids of the last few things of one kind, newest first.
     *
     * @return list<int>
     */
    public function recent(User $user, string $type, int $limit = self::KEEP): array
    {
        return RecentlyViewed::query()
            ->where('user_id', $user->id)
            ->where('viewable_type', $type)
            ->orderByDesc('viewed_at')
            ->take($limit)
            ->pluck('viewable_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Trims the tail, so one account's history cannot grow without bound.
     *
     * Called from the same place as record(), on a lottery rather than every
     * time: the table is tiny and the tidy-up is not worth a delete on every
     * page view.
     */
    public function prune(User $user, string $type, int $keep = 30): void
    {
        if (random_int(1, 20) !== 1) {
            return;
        }

        $cutoff = RecentlyViewed::query()
            ->where('user_id', $user->id)
            ->where('viewable_type', $type)
            ->orderByDesc('viewed_at')
            ->skip($keep)
            ->take(1)
            ->value('viewed_at');

        if ($cutoff === null) {
            return;
        }

        RecentlyViewed::query()
            ->where('user_id', $user->id)
            ->where('viewable_type', $type)
            ->where('viewed_at', '<=', $cutoff)
            ->delete();
    }
}
