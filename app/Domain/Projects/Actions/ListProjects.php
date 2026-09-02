<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Models\Post;
use App\Domain\Projects\DTOs\ProjectFilters;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The /projects reporting surface: every project with its post mix and spend.
 *
 * Two queries, always. The projects themselves, then one grouped aggregate over
 * their posts — not a subquery per column per row, and emphatically not a
 * count per project in PHP. Sorting by a computed figure happens after the
 * merge, in a collection, because the numbers do not exist in SQL until the
 * second query has run.
 *
 * Money is defined here exactly as GetDashboardMetrics defines it: spend is the
 * price of posts that went live in the window. Two screens that disagree about
 * what an advertiser spent last month is a support ticket, not a rounding
 * difference.
 */
final class ListProjects
{
    /** Statuses whose money has left the wallet. Matches the dashboard. */
    private const SPENT = [PostStatus::Posted, PostStatus::Completed];

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(User $user, ProjectFilters $filters): Collection
    {
        $projects = $this->query($user, $filters)->get();

        if ($projects->isEmpty()) {
            return collect();
        }

        $stats = $this->statsFor($projects->pluck('id')->all());

        $rows = $projects->map(fn (Project $project): array => $this->row(
            $project,
            $stats[$project->id] ?? [],
        ));

        return $this->sorted($rows, $filters)->values();
    }

    /**
     * Totals across the projects on screen.
     *
     * Summed from the same rows the table renders rather than recomputed, so
     * the footer can never disagree with the column above it.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function totals(Collection $rows): array
    {
        return [
            'posts' => (int) $rows->sum('posts.total'),
            'frozenCents' => (int) $rows->sum('frozenCents'),
            'spentMonthCents' => (int) $rows->sum('spentMonthCents'),
            'spentQuarterCents' => (int) $rows->sum('spentQuarterCents'),
        ];
    }

    /**
     * @return Builder<Project>
     */
    private function query(User $user, ProjectFilters $filters): Builder
    {
        return Project::query()
            ->where('user_id', $user->id)
            ->with('category:id,name')
            // A deterministic base order, which the stable sort downstream
            // preserves for rows whose sort key is equal.
            ->orderBy('id')
            ->when(
                $filters->statusEnum() !== null,
                fn (Builder $q) => $q->where('status', $filters->statusEnum()),
            )
            ->when($filters->search !== null, function (Builder $q) use ($filters): void {
                $term = '%'.addcslashes((string) $filters->search, '%_\\').'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('website_url', 'like', $term));
            });
    }

    /**
     * Every per-project figure, in one grouped query.
     *
     * The conditional aggregates are written as SUM(CASE WHEN …) rather than
     * with a database-specific helper, because the test suite runs on SQLite
     * and production runs on MySQL and this has to mean the same thing on both.
     *
     * @param  list<int>  $projectIds
     * @return array<int, array<string, int>>
     */
    private function statsFor(array $projectIds): array
    {
        $now = Carbon::now();
        $bindings = [
            'monthStart' => $now->copy()->startOfMonth(),
            'monthEnd' => $now->copy()->endOfMonth(),
            'lastMonthStart' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
            'lastMonthEnd' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
            'quarterStart' => $now->copy()->startOfQuarter(),
            'quarterEnd' => $now->copy()->endOfQuarter(),
        ];

        $spent = $this->inList(self::SPENT);
        $held = $this->inList(array_filter(
            PostStatus::cases(),
            static fn (PostStatus $status): bool => $status->holdsFrozenFunds(),
        ));

        $rows = Post::query()
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->selectRaw('project_id')
            ->selectRaw('count(*) as total')
            ->selectRaw($this->countWhere("status = 'new'").' as bucket_new')
            ->selectRaw($this->countWhere("status in ('in_progress', 'content_review')").' as bucket_progress')
            // Live and settled: verified, or never given a verification window.
            ->selectRaw($this->countWhere(
                "status = 'completed' or (status = 'posted' and (frozen_until is null or frozen_until <= ?))"
            ).' as bucket_posted', [$now])
            // Live but still inside the 3-day window, so the money is held.
            ->selectRaw($this->countWhere(
                "status = 'posted' and frozen_until is not null and frozen_until > ?"
            ).' as bucket_frozen', [$now])
            ->selectRaw($this->sumWhere('price_cents', "status in {$held}").' as frozen_cents')
            // Average is over completed posts only: a post that is still being
            // written has a quoted price, not a price anyone has paid.
            ->selectRaw($this->sumWhere('price_cents', "status = 'completed'").' as completed_cents')
            ->selectRaw($this->countWhere("status = 'completed'").' as completed_count')
            ->selectRaw($this->sumWhere(
                'price_cents',
                "status in {$spent} and published_at between ? and ?"
            ).' as spent_month', [$bindings['monthStart'], $bindings['monthEnd']])
            ->selectRaw($this->sumWhere(
                'price_cents',
                "status in {$spent} and published_at between ? and ?"
            ).' as spent_last_month', [$bindings['lastMonthStart'], $bindings['lastMonthEnd']])
            ->selectRaw($this->sumWhere(
                'price_cents',
                "status in {$spent} and published_at between ? and ?"
            ).' as spent_quarter', [$bindings['quarterStart'], $bindings['quarterEnd']])
            ->get();

        $out = [];

        foreach ($rows as $row) {
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
     * @param  array<string, int>  $stats
     * @return array<string, mixed>
     */
    private function row(Project $project, array $stats): array
    {
        $total = $stats['total'] ?? 0;
        $new = $stats['new'] ?? 0;
        $progress = $stats['progress'] ?? 0;
        $posted = $stats['posted'] ?? 0;
        $frozen = $stats['frozen'] ?? 0;

        $spentMonth = $stats['spentMonth'] ?? 0;
        $spentLastMonth = $stats['spentLastMonth'] ?? 0;
        $completedCount = $stats['completedCount'] ?? 0;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'websiteUrl' => $project->website_url,
            'category' => $project->category?->name,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'isArchived' => $project->status === ProjectStatus::Archived,
            'createdAt' => $project->created_at?->toIso8601String(),

            'posts' => [
                'total' => $total,
                'new' => $new,
                'progress' => $progress,
                'posted' => $posted,
                'frozen' => $frozen,
                // Draft, rejected, cancelled and refunded. Carried so the bar
                // adds up to the total printed above it — a stacked bar whose
                // segments do not sum to the number beside them is a lie.
                'other' => max(0, $total - $new - $progress - $posted - $frozen),
            ],

            'frozenCents' => $stats['frozenCents'] ?? 0,
            'averageCents' => $completedCount === 0
                ? null
                : intdiv($stats['completedCents'] ?? 0, $completedCount),
            'spentMonthCents' => $spentMonth,
            'spentQuarterCents' => $stats['spentQuarter'] ?? 0,

            // Null, not zero: going from nothing to something is not "up 100%",
            // and the chip says "New" for it rather than a bogus percentage.
            'spentMonthDeltaPct' => $spentLastMonth === 0
                ? ($spentMonth === 0 ? 0.0 : null)
                : round((($spentMonth - $spentLastMonth) / $spentLastMonth) * 100, 1),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sorted(Collection $rows, ProjectFilters $filters): Collection
    {
        $key = match ($filters->sort) {
            'name' => static fn (array $row): string => mb_strtolower((string) $row['name']),
            'posts' => static fn (array $row): int => (int) $row['posts']['total'],
            'created_at' => static fn (array $row): string => (string) $row['createdAt'],
            default => static fn (array $row): int => (int) $row['spentMonthCents'],
        };

        // The single-key form, deliberately: in Collection's multi-key form a
        // callable is treated as a *comparator* taking ($a, $b), not as a key
        // extractor, so passing one there sorts nothing and fails silently.
        //
        // The tiebreak comes free instead — PHP's sort is stable as of 8.0 and
        // the query below orders by id, so projects on identical spend keep a
        // deterministic order between requests.
        return $filters->direction === 'asc' ? $rows->sortBy($key) : $rows->sortByDesc($key);
    }

    /**
     * @param  list<PostStatus>  $statuses
     */
    private function inList(array $statuses): string
    {
        return '('.implode(', ', array_map(
            static fn (PostStatus $status): string => "'".$status->value."'",
            array_values($statuses),
        )).')';
    }

    private function countWhere(string $condition): string
    {
        return "sum(case when {$condition} then 1 else 0 end)";
    }

    private function sumWhere(string $column, string $condition): string
    {
        return "coalesce(sum(case when {$condition} then {$column} else 0 end), 0)";
    }
}
