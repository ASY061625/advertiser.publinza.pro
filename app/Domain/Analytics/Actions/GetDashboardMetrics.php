<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Billing\Models\Transaction;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every dashboard widget, in one payload.
 *
 * One action and one cache entry rather than six endpoints: the page is useless
 * half-loaded, and six independent caches would let the stat cards and the
 * chart disagree about the same range.
 */
final class GetDashboardMetrics
{
    /** Statuses whose work is still in flight. */
    private const IN_FLIGHT = [PostStatus::New, PostStatus::InProgress, PostStatus::ContentReview];

    /** Statuses whose money has been spent rather than merely committed. */
    private const SPENT = [PostStatus::Posted, PostStatus::Completed];

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, DateRange $range, string $granularity, ?int $projectId = null): array
    {
        $key = sprintf('dashboard:%d:%s:%s:%s', $user->id, $range->cacheKey(), $granularity, $projectId ?? 'all');

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember(
            $key,
            now()->addMinutes(5),
            fn (): array => $this->build($user, $range, $granularity, $projectId),
        );

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(User $user, DateRange $range, string $granularity, ?int $projectId): array
    {
        $previous = $range->previous();

        $hasProjects = Project::query()->where('user_id', $user->id)->exists();
        $hasAnyPosts = Post::query()->where('user_id', $user->id)->exists();

        return [
            'range' => [
                'key' => $range->key,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
                'label' => $range->label,
            ],
            'granularity' => $granularity,
            'projectId' => $projectId,

            // These two drive which empty state the page shows, and they are
            // deliberately unfiltered by range: "you have never made a post" is
            // a different situation from "nothing happened last week".
            'hasProjects' => $hasProjects,
            'hasAnyPosts' => $hasAnyPosts,

            'stats' => $this->stats($user, $range, $previous, $projectId),
            'series' => $this->series($user, $range, $granularity, $projectId),
            'statusBreakdown' => $this->statusBreakdown($user, $range, $projectId),
            'recentPosts' => $this->recentPosts($user, $projectId),
            'topWebsites' => $this->topWebsites($user, $range, $projectId),
            'deadlines' => $this->deadlines($user, $projectId),
        ];
    }

    /**
     * @return array<string, array{value: int, format: string, deltaPct: float|null}>
     */
    private function stats(User $user, DateRange $range, DateRange $previous, ?int $projectId): array
    {
        $wallet = $user->wallet;

        $spent = $this->spentBetween($user, $range->from, $range->to, $projectId);
        $spentBefore = $this->spentBetween($user, $previous->from, $previous->to, $projectId);

        $live = $this->livePostsBetween($user, $range->from, $range->to, $projectId);
        $liveBefore = $this->livePostsBetween($user, $previous->from, $previous->to, $projectId);

        // Point-in-time figures reconstructed from the ledger, which records the
        // balance after every movement — so "available balance a month ago" is a
        // real number rather than an estimate.
        $availableNow = $wallet?->available_cents ?? 0;
        $frozenNow = $wallet?->frozen_cents ?? 0;
        [$availableBefore, $frozenBefore] = $this->walletAt($user, $previous->to);

        $activeProjects = $this->activeProjectsAt($user, $range->to);
        $activeProjectsBefore = $this->activeProjectsAt($user, $previous->to);

        $inFlight = $this->inFlightAt($user, $range->to, $projectId);
        $inFlightBefore = $this->inFlightAt($user, $previous->to, $projectId);

        return [
            'totalSpent' => $this->stat($spent, $spentBefore, 'money'),
            'availableBalance' => $this->stat($availableNow, $availableBefore, 'money'),
            'frozenFunds' => $this->stat($frozenNow, $frozenBefore, 'money'),
            'activeProjects' => $this->stat($activeProjects, $activeProjectsBefore, 'count'),
            'postsInProgress' => $this->stat($inFlight, $inFlightBefore, 'count'),
            'liveLinks' => $this->stat($live, $liveBefore, 'count'),
        ];
    }

    /**
     * @return array{value: int, format: string, deltaPct: float|null}
     */
    private function stat(int $value, int $previous, string $format): array
    {
        return [
            'value' => $value,
            'format' => $format,
            // Null, not zero: going from nothing to something is not "up 100%",
            // and the chip renders "New" for it rather than a bogus percentage.
            'deltaPct' => $previous === 0
                ? ($value === 0 ? 0.0 : null)
                : round((($value - $previous) / $previous) * 100, 1),
        ];
    }

    private function spentBetween(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $projectId): int
    {
        return (int) $this->scopedPosts($user, $projectId)
            ->whereIn('status', self::SPENT)
            ->whereBetween('published_at', [$from, $to])
            ->sum('price_cents');
    }

    private function livePostsBetween(User $user, CarbonImmutable $from, CarbonImmutable $to, ?int $projectId): int
    {
        return $this->scopedPosts($user, $projectId)
            ->whereIn('status', self::SPENT)
            ->whereBetween('published_at', [$from, $to])
            ->count();
    }

    /**
     * The wallet as it stood at a moment, read off the newest ledger row at or
     * before it.
     *
     * @return array{int, int}
     */
    private function walletAt(User $user, CarbonImmutable $at): array
    {
        $wallet = $user->wallet;

        if ($wallet === null) {
            return [0, 0];
        }

        $row = Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('created_at', '<=', $at)
            ->latest('created_at')
            ->latest('id')
            ->first(['balance_after_cents', 'frozen_after_cents']);

        // No ledger row before that moment means the wallet was empty then.
        return [(int) ($row->balance_after_cents ?? 0), (int) ($row->frozen_after_cents ?? 0)];
    }

    private function activeProjectsAt(User $user, CarbonImmutable $at): int
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->where('status', ProjectStatus::Active)
            ->where('created_at', '<=', $at)
            ->count();
    }

    /**
     * How many posts were mid-flight at a moment.
     *
     * Reconstructed from post_status_history rather than the current status
     * column, because "in progress today" and "in progress a month ago" are
     * different questions and only the history can answer the second one.
     */
    private function inFlightAt(User $user, CarbonImmutable $at, ?int $projectId): int
    {
        $statuses = array_map(static fn (PostStatus $s): string => $s->value, self::IN_FLIGHT);

        return $this->scopedPosts($user, $projectId)
            ->where('posts.created_at', '<=', $at)
            ->whereRaw(
                '(select h.to_status from post_status_history h
                  where h.post_id = posts.id and h.created_at <= ?
                  order by h.created_at desc, h.id desc limit 1) in (?, ?, ?)',
                [$at->toDateTimeString(), ...$statuses],
            )
            ->count();
    }

    /**
     * @return list<array{label: string, iso: string, placements: int, spendCents: int}>
     */
    private function series(User $user, DateRange $range, string $granularity, ?int $projectId): array
    {
        $rows = $this->scopedPosts($user, $projectId)
            ->whereIn('status', self::SPENT)
            ->whereBetween('published_at', [$range->from, $range->to])
            ->get(['published_at', 'price_cents']);

        // Bucketed in PHP rather than SQL: the date functions differ between
        // MySQL and the SQLite the tests run on, and the row count here is
        // bounded by one advertiser's placements in one range.
        $buckets = [];

        foreach ($this->bucketStarts($range, $granularity) as $start) {
            $buckets[$start->toDateString()] = ['placements' => 0, 'spendCents' => 0];
        }

        foreach ($rows as $row) {
            $key = $this->bucketFor(CarbonImmutable::parse($row->published_at), $granularity)->toDateString();

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['placements']++;
            $buckets[$key]['spendCents'] += (int) $row->price_cents;
        }

        $out = [];

        foreach ($buckets as $iso => $values) {
            $out[] = [
                'iso' => $iso,
                'label' => $this->bucketLabel(CarbonImmutable::parse($iso), $granularity),
                'placements' => $values['placements'],
                'spendCents' => $values['spendCents'],
            ];
        }

        return $out;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function bucketStarts(DateRange $range, string $granularity): array
    {
        $starts = [];
        $cursor = $this->bucketFor($range->from, $granularity);

        while ($cursor->lessThanOrEqualTo($range->to)) {
            $starts[] = $cursor;

            $cursor = match ($granularity) {
                'month' => $cursor->addMonth(),
                'week' => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return $starts;
    }

    private function bucketFor(CarbonImmutable $moment, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'month' => $moment->startOfMonth(),
            'week' => $moment->startOfWeek(),
            default => $moment->startOfDay(),
        };
    }

    private function bucketLabel(CarbonImmutable $start, string $granularity): string
    {
        return match ($granularity) {
            'month' => $start->format('M Y'),
            'week' => $start->format('j M'),
            default => $start->format('j M'),
        };
    }

    /**
     * @return list<array{status: string, label: string, count: int, pct: float}>
     */
    private function statusBreakdown(User $user, DateRange $range, ?int $projectId): array
    {
        $counts = $this->scopedPosts($user, $projectId)
            ->whereBetween('created_at', [$range->from, $range->to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $counts->sum();

        $out = [];

        foreach (PostStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            if ($count === 0) {
                continue;
            }

            $out[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'badge' => $status->badgeKey(),
                'count' => $count,
                'pct' => $total === 0 ? 0.0 : round(($count / $total) * 100, 1),
            ];
        }

        return $out;
    }

    /**
     * The 8 most recent, across every project — deliberately not filtered by
     * the date range, because "recent" means recent.
     *
     * @return list<array<string, mixed>>
     */
    private function recentPosts(User $user, ?int $projectId): array
    {
        return $this->scopedPosts($user, $projectId)
            ->with(['website:id,domain', 'project:id,name'])
            ->latest('created_at')
            ->take(8)
            ->get()
            ->map(fn (Post $post): array => [
                'id' => $post->id,
                'domain' => $post->website?->domain ?? '',
                // Null until Publinza stores its own site marks. Pointing this
                // at a third-party favicon service would ship every domain the
                // advertiser is buying on to that service on each page load,
                // which is not a trade this product makes. The row falls back
                // to a glyph of the same size, so nothing shifts when it lands.
                'favicon' => null,
                'project' => $post->project?->name,
                'anchorText' => $post->anchor_text,
                'status' => $post->status->value,
                'statusLabel' => $post->status->label(),
                'badge' => $post->status->badgeKey(),
                'priceCents' => $post->price_cents,
                'createdAt' => $post->created_at?->toIso8601String(),
                'publishedUrl' => $post->published_url,
            ])
            ->all();
    }

    /**
     * @return list<array{domain: string, placements: int, totalCents: int}>
     */
    private function topWebsites(User $user, DateRange $range, ?int $projectId): array
    {
        return $this->scopedPosts($user, $projectId)
            ->whereIn('posts.status', self::SPENT)
            ->whereBetween('posts.published_at', [$range->from, $range->to])
            ->join('websites', 'websites.id', '=', 'posts.website_id')
            ->groupBy('websites.id', 'websites.domain')
            ->orderByDesc('total_cents')
            ->take(5)
            ->get([
                'websites.domain',
                DB::raw('count(*) as placements'),
                DB::raw('sum(posts.price_cents) as total_cents'),
            ])
            // The rows are aggregate projections, not Posts: `domain`,
            // `placements` and `total_cents` are select aliases, so they are
            // read off the attribute bag rather than as model properties.
            ->map(fn (Post $row): array => [
                'domain' => (string) $row->getAttribute('domain'),
                'placements' => (int) $row->getAttribute('placements'),
                'totalCents' => (int) $row->getAttribute('total_cents'),
            ])
            ->all();
    }

    /**
     * Posts due inside 7 days. Not range-filtered: a deadline is about what is
     * coming, not about the window being reported on.
     *
     * @return list<array<string, mixed>>
     */
    private function deadlines(User $user, ?int $projectId): array
    {
        return $this->scopedPosts($user, $projectId)
            ->whereIn('status', self::IN_FLIGHT)
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [now(), now()->addDays(7)])
            ->with(['website:id,domain'])
            ->orderBy('deadline_at')
            ->take(6)
            ->get()
            ->map(fn (Post $post): array => [
                'id' => $post->id,
                'domain' => $post->website?->domain ?? '',
                'statusLabel' => $post->status->label(),
                'badge' => $post->status->badgeKey(),
                'deadlineAt' => $post->deadline_at?->toIso8601String(),
                // Under 48 hours reads amber; the client decides the styling but
                // the threshold is decided once, here.
                'urgent' => $post->deadline_at !== null && $post->deadline_at->lessThan(now()->addHours(48)),
            ])
            ->all();
    }

    /**
     * @return Builder<Post>
     */
    private function scopedPosts(User $user, ?int $projectId)
    {
        return Post::query()
            ->where('posts.user_id', $user->id)
            ->when($projectId !== null, fn ($q) => $q->where('posts.project_id', $projectId));
    }
}
