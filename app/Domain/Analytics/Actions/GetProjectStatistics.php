<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DTOs\DateRange;
use App\Domain\Catalog\Enums\LinkType;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Everything the Statistics tab plots, for one project.
 *
 * Two queries carry the whole tab: the posts published inside the range, and
 * the posts ordered inside it. Everything else — the series, the summary, the
 * category and folder breakdowns, the table — is those rows bucketed, so no
 * two numbers on the screen can come from queries that disagree.
 *
 * "Published" is the event everything financial hangs off. A post's price is
 * spent when it goes live, not when it is ordered, which is why spend and
 * placements share the `published_at` bucket while "ordered" has its own.
 */
final class GetProjectStatistics
{
    public const GRANULARITIES = ['day', 'week', 'month'];

    /** Beyond this the horizontal bars stop being readable and start being a list. */
    private const TOP_CATEGORIES = 10;

    /**
     * @return array<string, mixed>
     */
    public function handle(Project $project, DateRange $range, string $granularity, ?int $folderId = null): array
    {
        $granularity = in_array($granularity, self::GRANULARITIES, true) ? $granularity : 'day';

        $published = $this->published($project, $folderId)
            ->whereBetween('posts.published_at', [$range->from, $range->to])
            ->get();

        $ordered = $this->scoped($project, $folderId)
            ->whereBetween('posts.created_at', [$range->from, $range->to])
            ->get(['posts.id', 'posts.created_at']);

        // Links already live when the range opened: the running total has to
        // start from the truth, not from zero, or a range that begins after
        // the project did would claim every link was built inside it.
        $carriedLinks = $this->published($project, $folderId)
            ->where('posts.published_at', '<', $range->from)
            ->count();

        $series = $this->series($published, $ordered, $range, $granularity, $carriedLinks);

        return [
            'range' => [
                'key' => $range->key,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
                'label' => $range->label,
            ],
            'granularity' => $granularity,
            'folderId' => $folderId,
            'summary' => $this->summary($project, $range, $folderId),
            'series' => $series,
            'byCategory' => $this->breakdown($published, 'category'),
            'byFolder' => $this->breakdown($published, 'folder'),
            'hasEverHadPosts' => $this->scoped($project, null)->exists(),
        ];
    }

    /**
     * Posts that went live, with the two website facts the charts group by.
     *
     * @return Builder<Post>
     */
    private function published(Project $project, ?int $folderId): Builder
    {
        return $this->scoped($project, $folderId)
            ->whereNotNull('posts.published_at')
            ->whereIn('posts.status', [PostStatus::Posted->value, PostStatus::Completed->value])
            ->join('websites', 'websites.id', '=', 'posts.website_id')
            ->leftJoin('website_categories', 'website_categories.id', '=', 'websites.category_id')
            ->leftJoin('project_folders', 'project_folders.id', '=', 'posts.folder_id')
            ->select([
                'posts.id',
                'posts.price_cents',
                'posts.published_at',
                'websites.link_type',
                'website_categories.name as category_name',
                'project_folders.name as folder_name',
            ]);
    }

    /**
     * @return Builder<Post>
     */
    private function scoped(Project $project, ?int $folderId): Builder
    {
        return Post::query()
            ->where('posts.project_id', $project->id)
            ->when($folderId !== null, fn (Builder $q) => $q->where('posts.folder_id', $folderId));
    }

    /**
     * One row per period, with every figure the charts and the table read.
     *
     * Empty periods are kept. A gap in the data is information — dropping the
     * weeks with no placements would draw a line straight from March to May and
     * call it continuity.
     *
     * @param  Collection<int, Post>  $published
     * @param  Collection<int, Post>  $ordered
     * @return list<array<string, mixed>>
     */
    private function series(
        Collection $published,
        Collection $ordered,
        DateRange $range,
        string $granularity,
        int $carriedLinks,
    ): array {
        $buckets = [];

        foreach ($this->bucketStarts($range, $granularity) as $start) {
            $buckets[$start->toDateString()] = [
                'iso' => $start->toDateString(),
                'label' => $this->label($start, $granularity),
                'ordered' => 0,
                'publishedCount' => 0,
                'dofollow' => 0,
                'nofollow' => 0,
                'spendCents' => 0,
            ];
        }

        foreach ($ordered as $post) {
            $key = $this->bucketFor(CarbonImmutable::parse((string) $post->getAttribute('created_at')), $granularity)
                ->toDateString();

            if (isset($buckets[$key])) {
                $buckets[$key]['ordered']++;
            }
        }

        foreach ($published as $post) {
            $key = $this->bucketFor(CarbonImmutable::parse((string) $post->getAttribute('published_at')), $granularity)
                ->toDateString();

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['publishedCount']++;
            $buckets[$key]['spendCents'] += (int) $post->getAttribute('price_cents');

            $follow = $post->getAttribute('link_type') === LinkType::Nofollow->value ? 'nofollow' : 'dofollow';
            $buckets[$key][$follow]++;
        }

        $cumulativeSpend = 0;
        $liveLinks = $carriedLinks;
        $out = [];

        foreach ($buckets as $bucket) {
            $cumulativeSpend += $bucket['spendCents'];
            $liveLinks += $bucket['dofollow'] + $bucket['nofollow'];

            $out[] = $bucket + [
                'cumulativeSpendCents' => $cumulativeSpend,
                'liveLinks' => $liveLinks,
                'averageCents' => $bucket['publishedCount'] === 0
                    ? null
                    : intdiv($bucket['spendCents'], $bucket['publishedCount']),
            ];
        }

        return $out;
    }

    /**
     * The four cards, each against the equivalent window before this one.
     *
     * @return array<string, mixed>
     */
    private function summary(Project $project, DateRange $range, ?int $folderId): array
    {
        $now = $this->totals($project, $range, $folderId);
        $then = $this->totals($project, $range->previous(), $folderId);

        $liveNow = $this->liveLinksAt($project, $range->to, $folderId);
        $liveThen = $this->liveLinksAt($project, $range->previous()->to, $folderId);

        return [
            'spentCents' => $now['spentCents'],
            'spentDeltaPct' => $this->delta($then['spentCents'], $now['spentCents']),
            'published' => $now['published'],
            'publishedDeltaPct' => $this->delta($then['published'], $now['published']),
            // Live links is a running total, not a count inside the window:
            // it answers "how many of my links are out there", which is a
            // different question from "how many did I add this month". Without
            // that distinction it would be the same number as the card beside
            // it, printed twice.
            'links' => $liveNow,
            'linksDeltaPct' => $this->delta($liveThen, $liveNow),
            'averageCents' => $now['published'] === 0 ? null : intdiv($now['spentCents'], $now['published']),
            'averageDeltaPct' => $this->delta(
                $then['published'] === 0 ? 0 : intdiv($then['spentCents'], $then['published']),
                $now['published'] === 0 ? 0 : intdiv($now['spentCents'], $now['published']),
            ),
        ];
    }

    /** Every link live on this project by a given moment. */
    private function liveLinksAt(Project $project, CarbonImmutable $moment, ?int $folderId): int
    {
        return $this->scoped($project, $folderId)
            ->whereNotNull('posts.published_at')
            ->whereIn('posts.status', [PostStatus::Posted->value, PostStatus::Completed->value])
            ->where('posts.published_at', '<=', $moment)
            ->count();
    }

    /**
     * @return array{spentCents: int, published: int}
     */
    private function totals(Project $project, DateRange $range, ?int $folderId): array
    {
        $row = $this->scoped($project, $folderId)
            ->whereNotNull('posts.published_at')
            ->whereIn('posts.status', [PostStatus::Posted->value, PostStatus::Completed->value])
            ->whereBetween('posts.published_at', [$range->from, $range->to])
            ->selectRaw('coalesce(sum(price_cents), 0) as spent, count(*) as placements')
            ->first();

        return [
            'spentCents' => (int) ($row?->getAttribute('spent') ?? 0),
            'published' => (int) ($row?->getAttribute('placements') ?? 0),
        ];
    }

    /**
     * Null rather than zero when there is nothing to compare against: going
     * from nothing to something is not "up 100%", and the chip says "New".
     */
    private function delta(int $before, int $after): ?float
    {
        if ($before === 0) {
            return $after === 0 ? 0.0 : null;
        }

        return round((($after - $before) / $before) * 100, 1);
    }

    /**
     * Spend grouped by one of the published rows' own columns.
     *
     * Everything past the top ten folds into "Other" rather than being cut:
     * a breakdown whose bars do not add up to the spend on the card above is
     * describing a different project.
     *
     * @param  Collection<int, Post>  $published
     * @return list<array{label: string, spentCents: int, placements: int}>
     */
    private function breakdown(Collection $published, string $key): array
    {
        $column = $key === 'folder' ? 'folder_name' : 'category_name';

        $groups = [];

        foreach ($published as $post) {
            $label = (string) ($post->getAttribute($column) ?? ($key === 'folder' ? 'No folder' : 'Uncategorised'));

            $groups[$label] ??= ['label' => $label, 'spentCents' => 0, 'placements' => 0];
            $groups[$label]['spentCents'] += (int) $post->getAttribute('price_cents');
            $groups[$label]['placements']++;
        }

        $rows = array_values($groups);
        usort($rows, static fn (array $a, array $b): int => $b['spentCents'] <=> $a['spentCents']);

        if (count($rows) <= self::TOP_CATEGORIES) {
            return $rows;
        }

        $top = array_slice($rows, 0, self::TOP_CATEGORIES);
        $rest = array_slice($rows, self::TOP_CATEGORIES);

        $top[] = [
            'label' => 'Other',
            'spentCents' => array_sum(array_column($rest, 'spentCents')),
            'placements' => array_sum(array_column($rest, 'placements')),
        ];

        return $top;
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

    private function label(CarbonImmutable $start, string $granularity): string
    {
        return match ($granularity) {
            'month' => $start->format('M Y'),
            'week' => 'w/c '.$start->format('j M'),
            default => $start->format('j M'),
        };
    }
}
