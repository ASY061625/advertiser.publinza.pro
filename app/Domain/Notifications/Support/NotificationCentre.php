<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use App\Domain\Identity\Enums\NotificationChannel;
use App\Domain\Identity\Enums\NotificationEvent;
use App\Domain\Identity\Support\NotificationSettings;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the drawer reads.
 *
 * Three jobs: fetch a page of somebody's notifications, put each one in a day
 * bucket, and fold a burst of the same kind into one line. All three happen
 * here rather than in the client, because "yesterday" depends on the reader's
 * timezone — which the server knows and a component several levels deep does
 * not — and because the collapse rule has to agree with itself between the
 * All tab and the Unread tab.
 */
final class NotificationCentre
{
    /** A page. Enough that scrolling is rare, small enough to send eagerly. */
    public const PAGE = 30;

    /**
     * Below this, a group is not worth hiding.
     *
     * Two items collapsed into "2 posts published" saves one line and costs a
     * click. Three is where the summary starts being the more useful thing to
     * read.
     */
    private const COLLAPSE_AT = 3;

    /**
     * @return array{groups: list<array<string, mixed>>, counts: array{all: int, unread: int}, hasMore: bool, pushWanted: bool}
     */
    public function forUser(User $user, bool $unreadOnly = false, int $page = 1): array
    {
        $query = $user->notifications()->getQuery();

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $rows = $query
            ->orderByDesc('created_at')
            ->skip(($page - 1) * self::PAGE)
            // One past the page, so "is there more" is answered without a
            // second count over a table that only grows.
            ->take(self::PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PAGE;
        $rows = $rows->take(self::PAGE);

        return [
            'groups' => $this->buckets($rows, $user->timezone ?: config('app.timezone')),
            'counts' => [
                'all' => $user->notifications()->count(),
                'unread' => $user->unreadNotifications()->count(),
            ],
            'hasMore' => $hasMore,
            /*
             * Whether this account has asked for browser notifications at all.
             *
             * The drawer only offers the permission prompt when the answer is
             * yes. A prompt nobody asked for is the one everybody denies, and
             * a denial in Chrome is permanent.
             */
            'pushWanted' => $this->wantsPushAnywhere($user),
        ];
    }

    private function wantsPushAnywhere(User $user): bool
    {
        $settings = app(NotificationSettings::class);

        foreach (NotificationEvent::cases() as $event) {
            if ($settings->wants($user, $event, NotificationChannel::Push)) {
                return true;
            }
        }

        return false;
    }

    /** Just the badge. Called on every page load, so it stays a count. */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Today / Yesterday / Earlier, in the reader's own timezone.
     *
     * @param  Collection<int, DatabaseNotification>  $rows
     * @return list<array<string, mixed>>
     */
    private function buckets(Collection $rows, string $timezone): array
    {
        $today = Carbon::now($timezone)->startOfDay();
        $yesterday = $today->copy()->subDay();

        /** @var array<string, list<array<string, mixed>>> $buckets */
        $buckets = ['today' => [], 'yesterday' => [], 'earlier' => []];

        foreach ($rows as $row) {
            $at = $row->created_at?->copy()->setTimezone($timezone);

            $key = match (true) {
                $at === null => 'earlier',
                $at->greaterThanOrEqualTo($today) => 'today',
                $at->greaterThanOrEqualTo($yesterday) => 'yesterday',
                default => 'earlier',
            };

            $buckets[$key][] = $this->item($row);
        }

        $labels = ['today' => 'Today', 'yesterday' => 'Yesterday', 'earlier' => 'Earlier'];

        $out = [];

        foreach ($buckets as $key => $items) {
            if ($items === []) {
                continue;
            }

            $out[] = [
                'key' => $key,
                'label' => $labels[$key],
                'items' => $this->collapse($items),
            ];
        }

        return $out;
    }

    /**
     * Fold runs of the same type into one expandable line.
     *
     * *Runs*, not "everything of this type in the bucket": three publications
     * this morning and one this afternoon with a rejection between them is two
     * separate stories, and merging them across the rejection would put the
     * summary in the wrong place on the timeline. Consecutive only.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function collapse(array $items): array
    {
        $out = [];

        /** @var list<array<string, mixed>> $run */
        $run = [];

        foreach ($items as $item) {
            if ($run !== [] && $run[0]['type'] !== $item['type']) {
                array_push($out, ...$this->fold($run));
                $run = [];
            }

            $run[] = $item;
        }

        if ($run !== []) {
            array_push($out, ...$this->fold($run));
        }

        return $out;
    }

    /**
     * One run: either folded into a single group, or handed back as it came.
     *
     * A plain method rather than a closure over `$out` and `$run`. The by-ref
     * version worked but was opaque to static analysis — which read the whole
     * body as unreachable and stopped checking it — and a function that
     * silently loses its type checking is a function that will grow a bug.
     *
     * @param  list<array<string, mixed>>  $run
     * @return list<array<string, mixed>>
     */
    private function fold(array $run): array
    {
        $type = NotificationType::tryFrom((string) $run[0]['type']);

        if ($type === null || ! $type->collapsible() || count($run) < self::COLLAPSE_AT) {
            return $run;
        }

        $unread = array_values(array_filter($run, static fn (array $item): bool => (bool) $item['unread']));

        return [[
            'kind' => 'group',
            // Derived from the first item, so the key is stable across a
            // re-read as long as the run is.
            'id' => 'group-'.$run[0]['id'],
            'type' => $type->value,
            'title' => $type->summary(count($run)),
            'icon' => $type->icon(),
            'tone' => $type->tone(),
            'at' => $run[0]['at'],
            'unread' => $unread !== [],
            'unreadCount' => count($unread),
            'items' => $run,
        ]];
    }

    /**
     * One stored row, as the client reads it.
     *
     * The title and body come straight off `data` — they were written as
     * finished sentences when the notification was sent, so a row about a post
     * that has since been deleted still reads correctly.
     *
     * @return array<string, mixed>
     */
    private function item(DatabaseNotification $row): array
    {
        /** @var array<string, mixed> $data */
        $data = $row->data;

        return [
            'kind' => 'item',
            'id' => $row->id,
            'type' => (string) ($data['type'] ?? 'unknown'),
            'title' => (string) ($data['title'] ?? 'Update'),
            'body' => (string) ($data['body'] ?? ''),
            'href' => (string) ($data['href'] ?? '/notifications'),
            'icon' => (string) ($data['icon'] ?? 'info'),
            'tone' => (string) ($data['tone'] ?? 'info'),
            'at' => $row->created_at?->toIso8601String(),
            'unread' => $row->read_at === null,
        ];
    }
}
