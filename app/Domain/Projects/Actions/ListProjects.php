<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\DTOs\ProjectFilters;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\ProjectStats;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(User $user, ProjectFilters $filters): Collection
    {
        $projects = $this->query($user, $filters)->get();

        if ($projects->isEmpty()) {
            return collect();
        }

        $stats = ProjectStats::forProjects($projects->pluck('id')->all());

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
     * @param  array<string, int>  $stats
     * @return array<string, mixed>
     */
    private function row(Project $project, array $stats): array
    {
        $spentMonth = $stats['spentMonth'] ?? 0;
        $spentLastMonth = $stats['spentLastMonth'] ?? 0;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'websiteUrl' => $project->website_url,
            'category' => $project->category?->name,
            'color' => $project->color,
            'status' => $project->status->value,
            'statusLabel' => $project->status->label(),
            'isArchived' => $project->status === ProjectStatus::Archived,
            'createdAt' => $project->created_at?->toIso8601String(),

            // Derived in ProjectStats so this page and the project's own page
            // cannot disagree about the same project.
            'posts' => ProjectStats::mix($stats),

            'frozenCents' => $stats['frozenCents'] ?? 0,
            'averageCents' => ProjectStats::averageCents($stats),
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
}
