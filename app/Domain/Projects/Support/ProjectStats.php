<?php

declare(strict_types=1);

namespace App\Domain\Projects\Support;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use Illuminate\Support\Carbon;

/**
 * The per-project post mix and money, in one grouped query.
 *
 * Shared by the projects list and a project's own page so the two cannot
 * disagree about the same project. The list showing 34 posts and the page
 * showing 31 is a support ticket, and it is exactly what happens when two
 * screens each write their own version of "how many are in progress".
 *
 * Conditional aggregates rather than a database-specific helper: production is
 * MySQL and the test suite is SQLite, and this has to mean the same thing on
 * both.
 */
final class ProjectStats
{
    /** Statuses whose money has left the wallet. */
    public const SPENT = [PostStatus::Posted, PostStatus::Completed];

    /**
     * Every figure, keyed by project id.
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, int>>
     */
    public static function forProjects(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $now = Carbon::now();
        $spent = self::inList(self::SPENT);
        $held = self::inList(array_filter(
            PostStatus::cases(),
            static fn (PostStatus $status): bool => $status->holdsFrozenFunds(),
        ));

        $windows = [
            'spent_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'spent_last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ],
            'spent_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
        ];

        $query = Post::query()
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->selectRaw('project_id')
            ->selectRaw('count(*) as total')
            ->selectRaw(self::countWhere("status = 'new'").' as bucket_new')
            ->selectRaw(self::countWhere("status in ('in_progress', 'content_review')").' as bucket_progress')
            // Live and settled: verified, or never given a verification window.
            ->selectRaw(self::countWhere(
                "status = 'completed' or (status = 'posted' and (frozen_until is null or frozen_until <= ?))"
            ).' as bucket_posted', [$now])
            // Live but still inside the window, so the money is held.
            ->selectRaw(self::countWhere(
                "status = 'posted' and frozen_until is not null and frozen_until > ?"
            ).' as bucket_frozen', [$now])
            ->selectRaw(self::sumWhere('price_cents', "status in {$held}").' as frozen_cents')
            // Averaged over completed posts only: a post still being written
            // has a quoted price, not a price anyone has paid.
            ->selectRaw(self::sumWhere('price_cents', "status = 'completed'").' as completed_cents')
            ->selectRaw(self::countWhere("status = 'completed'").' as completed_count');

        foreach ($windows as $alias => [$from, $to]) {
            $query->selectRaw(
                self::sumWhere('price_cents', "status in {$spent} and published_at between ? and ?").' as '.$alias,
                [$from, $to],
            );
        }

        $out = [];

        foreach ($query->get() as $row) {
            $out[(int) $row->getAttribute('project_id')] = array_map(
                static fn (mixed $value): int => (int) $value,
                [
                    'total' => $row->getAttribute('total'),
                    'new' => $row->getAttribute('bucket_new'),
                    'progress' => $row->getAttribute('bucket_progress'),
                    'posted' => $row->getAttribute('bucket_posted'),
                    'frozen' => $row->getAttribute('bucket_frozen'),
                    'frozenCents' => $row->getAttribute('frozen_cents'),
                    'completedCents' => $row->getAttribute('completed_cents'),
                    'completedCount' => $row->getAttribute('completed_count'),
                    'spentMonth' => $row->getAttribute('spent_month'),
                    'spentLastMonth' => $row->getAttribute('spent_last_month'),
                    'spentQuarter' => $row->getAttribute('spent_quarter'),
                ],
            );
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public static function forProject(int $projectId): array
    {
        return self::forProjects([$projectId])[$projectId] ?? self::empty();
    }

    /**
     * A project with no posts still has to render every figure.
     *
     * @return array<string, int>
     */
    public static function empty(): array
    {
        return [
            'total' => 0, 'new' => 0, 'progress' => 0, 'posted' => 0, 'frozen' => 0,
            'frozenCents' => 0, 'completedCents' => 0, 'completedCount' => 0,
            'spentMonth' => 0, 'spentLastMonth' => 0, 'spentQuarter' => 0,
        ];
    }

    /**
     * The four named buckets plus the remainder, so the widths always sum to
     * the total printed beside them.
     *
     * @param  array<string, int>  $stats
     * @return array<string, int>
     */
    public static function mix(array $stats): array
    {
        $total = $stats['total'] ?? 0;
        $named = ($stats['new'] ?? 0) + ($stats['progress'] ?? 0)
            + ($stats['posted'] ?? 0) + ($stats['frozen'] ?? 0);

        return [
            'total' => $total,
            'new' => $stats['new'] ?? 0,
            'progress' => $stats['progress'] ?? 0,
            'posted' => $stats['posted'] ?? 0,
            'frozen' => $stats['frozen'] ?? 0,
            // Draft, rejected, cancelled and refunded.
            'other' => max(0, $total - $named),
        ];
    }

    /**
     * Null rather than zero: zero would read as "these placements are free".
     *
     * @param  array<string, int>  $stats
     */
    public static function averageCents(array $stats): ?int
    {
        $count = $stats['completedCount'] ?? 0;

        return $count === 0 ? null : intdiv($stats['completedCents'] ?? 0, $count);
    }

    /**
     * @param  list<PostStatus>  $statuses
     */
    private static function inList(array $statuses): string
    {
        return '('.implode(', ', array_map(
            static fn (PostStatus $status): string => "'".$status->value."'",
            array_values($statuses),
        )).')';
    }

    private static function countWhere(string $condition): string
    {
        return "sum(case when {$condition} then 1 else 0 end)";
    }

    private static function sumWhere(string $column, string $condition): string
    {
        return "coalesce(sum(case when {$condition} then {$column} else 0 end), 0)";
    }
}
