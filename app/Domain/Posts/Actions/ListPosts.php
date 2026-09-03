<?php

declare(strict_types=1);

namespace App\Domain\Posts\Actions;

use App\Domain\Messaging\Enums\SenderType;
use App\Domain\Posts\DTOs\PostFilters;
use App\Domain\Posts\Enums\PostStatus;
use App\Domain\Posts\Enums\PostTab;
use App\Domain\Posts\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The /posts grid: one filtered, sorted, paginated query plus the tab counts.
 *
 * Everything happens in SQL. A grid that can hold every post an advertiser has
 * ever bought cannot filter or sort in PHP, and paginating after the fact would
 * mean loading the lot to show twenty-five rows.
 */
final class ListPosts
{
    /**
     * @return LengthAwarePaginator<Post>
     */
    public function paginate(User $user, PostFilters $filters): LengthAwarePaginator
    {
        return $this->sorted($this->filtered($user, $filters), $filters)
            ->with([
                'website:id,domain,country_id,primary_language_id,category_id',
                // latestOfMany builds a self-join, so a narrowed select here makes
                // its shared column names ambiguous. The row is four columns wide.
                'website.latestMetric',
                'project:id,name',
                'folder:id,name',
            ])
            // One correlated EXISTS per row rather than a count: the grid only
            // needs to know whether there is anything unread, and the board's
            // dot and the row's marker both read the same boolean.
            ->withExists(['conversations as has_unread' => fn (Builder $q) => $q
                ->whereHas('messages', fn (Builder $m) => $m
                    ->whereNull('messages.read_at')
                    ->where('messages.sender_type', '!=', SenderType::User->value))])
            ->paginate($filters->perPage)
            ->withQueryString();
    }

    /**
     * Every id the current filters match, ignoring pagination — what
     * "select all matching" and a filtered export both need.
     *
     * @return list<int>
     */
    public function matchingIds(User $user, PostFilters $filters, int $limit = 10_000): array
    {
        return $this->sorted($this->filtered($user, $filters), $filters)
            ->limit($limit)
            ->pluck('posts.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Counts for every tab, under the current filters minus the tab itself —
     * so the numbers say "how many would I see if I clicked there", which is
     * the only reading that makes a tab count useful.
     *
     * @return array<string, int>
     */
    public function tabCounts(User $user, PostFilters $filters): array
    {
        $rows = $this->filtered($user, $filters, applyTab: false)
            ->reorder()
            ->groupBy('posts.status')
            ->pluck(DB::raw('count(*)'), 'posts.status');

        $counts = [];
        $total = 0;

        foreach (PostTab::cases() as $tab) {
            if ($tab === PostTab::All) {
                continue;
            }

            $count = 0;

            foreach ($tab->statuses() as $status) {
                $count += (int) ($rows[$status->value] ?? 0);
            }

            $counts[$tab->value] = $count;
            $total += $count;
        }

        // All is the sum rather than its own query: they must agree, and one
        // query that can disagree with another is a bug waiting to be filed.
        return [PostTab::All->value => $total, ...$counts];
    }

    /**
     * @return Builder<Post>
     */
    public function filtered(User $user, PostFilters $filters, bool $applyTab = true): Builder
    {
        $query = Post::query()->where('posts.user_id', $user->id);

        // Applied before anything the request can influence, and never read
        // back out of the query string: a project's Post management tab is
        // that project's posts, and no crafted `projects[]` widens it.
        if ($filters->projectScope !== null) {
            $query->where('posts.project_id', $filters->projectScope);
        }

        if ($applyTab && $filters->tab !== PostTab::All) {
            $query->whereIn('posts.status', array_map(
                static fn (PostStatus $s): string => $s->value,
                $filters->tab->statuses(),
            ));
        }

        $this->applySearch($query, $filters);
        $this->applyScopeFilters($query, $filters);
        $this->applyWebsiteFilters($query, $filters);
        $this->applyDates($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function applySearch(Builder $query, PostFilters $filters): void
    {
        if ($filters->search === null) {
            return;
        }

        $term = '%'.addcslashes($filters->search, '%_\\').'%';

        $query->where(function (Builder $q) use ($filters, $term): void {
            $q->whereHas('website', fn (Builder $w) => $w->where('domain', 'like', $term))
                ->orWhere('posts.anchor_text', 'like', $term)
                ->orWhere('posts.target_url', 'like', $term);

            // "1042" should find post 1042, not every post whose anchor happens
            // to contain those digits — so an all-digit term also matches on id.
            if (ctype_digit($filters->search)) {
                $q->orWhere('posts.id', (int) $filters->search);
            }
        });
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function applyScopeFilters(Builder $query, PostFilters $filters): void
    {
        $query
            ->when($filters->projects !== [], fn (Builder $q) => $q->whereIn('posts.project_id', $filters->projects))
            ->when($filters->statuses !== [], fn (Builder $q) => $q->whereIn('posts.status', $filters->statuses))
            ->when($filters->folderId !== null, fn (Builder $q) => $q->where('posts.folder_id', $filters->folderId))
            ->when($filters->contentMode !== null, fn (Builder $q) => $q->where('posts.content_mode', $filters->contentMode))
            ->when($filters->minPriceCents !== null, fn (Builder $q) => $q->where('posts.price_cents', '>=', $filters->minPriceCents))
            ->when($filters->maxPriceCents !== null, fn (Builder $q) => $q->where('posts.price_cents', '<=', $filters->maxPriceCents));

        foreach (['anchorContains' => 'posts.anchor_text', 'targetUrlContains' => 'posts.target_url'] as $property => $column) {
            $value = $filters->{$property};

            if ($value !== null) {
                $query->where($column, 'like', '%'.addcslashes($value, '%_\\').'%');
            }
        }

        if ($filters->unreadOnly) {
            // Unread means unread *by the advertiser*, so their own messages
            // never count however they are stored. The advertiser is
            // SenderType::User here — admin and system are the other side.
            $query->whereHas('conversations.messages', fn (Builder $m) => $m
                ->whereNull('messages.read_at')
                ->where('messages.sender_type', '!=', SenderType::User->value));
        }

        if ($filters->deadlineWithin !== null) {
            $query->whereNotNull('posts.deadline_at');

            if ($filters->deadlineWithin === 'overdue') {
                $query->where('posts.deadline_at', '<', now());
            } else {
                $hours = ['24h' => 24, '3d' => 72, '7d' => 168][$filters->deadlineWithin];
                $query->whereBetween('posts.deadline_at', [now(), now()->addHours($hours)]);
            }
        }
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function applyWebsiteFilters(Builder $query, PostFilters $filters): void
    {
        $columns = [
            'categories' => 'category_id',
            'countries' => 'country_id',
            'languages' => 'primary_language_id',
        ];

        foreach ($columns as $property => $column) {
            $values = $filters->{$property};

            if ($values !== []) {
                $query->whereHas('website', fn (Builder $w) => $w->whereIn($column, $values));
            }
        }

        $metrics = [
            ['minDr', 'ahrefs_dr', '>='],
            ['maxDr', 'ahrefs_dr', '<='],
            ['minTraffic', 'monthly_traffic', '>='],
            ['maxTraffic', 'monthly_traffic', '<='],
        ];

        foreach ($metrics as [$property, $column, $operator]) {
            $value = $filters->{$property};

            if ($value !== null) {
                $query->whereHas('website.latestMetric', fn (Builder $m) => $m->where($column, $operator, $value));
            }
        }
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function applyDates(Builder $query, PostFilters $filters): void
    {
        if ($filters->dateFrom === null && $filters->dateTo === null) {
            return;
        }

        $column = $filters->dateField === 'published' ? 'posts.published_at' : 'posts.created_at';

        if ($filters->dateFrom !== null) {
            $query->where($column, '>=', Carbon::parse($filters->dateFrom)->startOfDay());
        }

        if ($filters->dateTo !== null) {
            $query->where($column, '<=', Carbon::parse($filters->dateTo)->endOfDay());
        }
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    private function sorted(Builder $query, PostFilters $filters): Builder
    {
        $direction = $filters->direction;

        // Sorting by a related column needs a join, not a subquery: the grid
        // pages through it, and a correlated subquery per row does not.
        $sorted = match ($filters->sort) {
            'domain' => $query
                ->join('websites as sort_site', 'sort_site.id', '=', 'posts.website_id')
                ->orderBy('sort_site.domain', $direction)
                ->select('posts.*'),
            'project' => $query
                ->join('projects as sort_project', 'sort_project.id', '=', 'posts.project_id')
                ->orderBy('sort_project.name', $direction)
                ->select('posts.*'),
            'price' => $query->orderBy('posts.price_cents', $direction),
            'id' => $query->orderBy('posts.id', $direction),
            // Nulls are "not yet", so they belong at the far end of the sort
            // rather than clumped at the top of a descending list.
            'published_at', 'deadline_at' => $query
                ->orderByRaw(sprintf('posts.%s is null', $filters->sort))
                ->orderBy('posts.'.$filters->sort, $direction),
            'anchor_text', 'status' => $query->orderBy('posts.'.$filters->sort, $direction),
            default => $query->orderBy('posts.created_at', $direction),
        };

        // A stable tiebreak, or two posts created in the same second can swap
        // places between pages and one of them is never seen.
        return $sorted->orderBy('posts.id', 'desc');
    }
}
