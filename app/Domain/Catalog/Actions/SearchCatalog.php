<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Enums\PublicationSpeed;
use App\Domain\Catalog\Models\Website;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;

/**
 * The catalog query, from fourteen filter groups to one page of sites.
 *
 * Text goes through Meilisearch; everything else is SQL against indexed
 * columns. That split is deliberate and worth stating, because "search" and
 * "filter" get conflated:
 *
 *   - Scout's builder is not an Eloquent builder. It cannot be joined, so a
 *     query that needs the metrics join, the price join and three per-user
 *     exists() clauses cannot be expressed through it.
 *   - Three of the filters are about *this advertiser* — their blacklist,
 *     their favourites, what they have already bought for this project. Those
 *     cannot live in a shared index without indexing every user against every
 *     site.
 *
 * So the engine answers "which sites match these words", and the database
 * answers everything else. The ids from the first constrain the second.
 */
final class SearchCatalog
{
    /** How many ids the engine is asked for before the database narrows them. */
    private const SEARCH_DEPTH = 1000;

    /**
     * @return CursorPaginator<Website>
     */
    public function handle(CatalogFilters $filters, ?int $userId = null): CursorPaginator
    {
        return $this->query($filters, $userId)
            ->cursorPaginate($filters->perPage, ['*'], 'cursor', Cursor::fromEncoded($filters->cursor));
    }

    /** How many sites match, for the "412 sites" count and the facet chips. */
    public function count(CatalogFilters $filters, ?int $userId = null): int
    {
        // Ordering is what cursor pagination needs and a count does not; the
        // aliases it selects would also make this a grouped query for nothing.
        return $this->query($filters, $userId, ordered: false)->count('websites.id');
    }

    /**
     * The filtered query, without pagination.
     *
     * @return Builder<Website>
     */
    public function query(CatalogFilters $filters, ?int $userId = null, bool $ordered = true): Builder
    {
        $query = Website::query()
            ->active()
            ->with(['category', 'primaryLanguage', 'country', 'latestMetric', 'prices'])
            ->joinSub(LatestWebsiteMetrics::query(), 'metrics', 'metrics.website_id', '=', 'websites.id')
            ->leftJoin('website_prices', function ($join): void {
                $join->on('website_prices.website_id', '=', 'websites.id')
                    ->where('website_prices.service_type', '=', 'article_placement');
            })
            // Plain "table.column as alias" strings, not raw expressions.
            // The cursor paginator reads the select list to map an ordering
            // alias back to the column it came from, and it does that by
            // string-matching " as " — an Expression object there is not a
            // string, and the alias would reach the WHERE clause unresolved,
            // which MySQL rejects outright.
            ->select([
                'websites.*',
                'metrics.monthly_traffic as sort_traffic',
                'metrics.ahrefs_dr as sort_dr',
                'website_prices.price_cents as sort_price',
            ]);

        if ($filters->query !== null) {
            $ids = Website::search($filters->query)->take(self::SEARCH_DEPTH)->keys();

            // An empty result set has to stay empty. whereIn on [] does that;
            // omitting the clause would quietly return the whole catalog.
            $query->whereIn('websites.id', $ids->all());
        }

        $this->constrain($query, $filters, $userId);

        return $ordered ? $this->order($query, $filters) : $query;
    }

    /**
     * @param  Builder<Website>  $query
     */
    private function constrain(Builder $query, CatalogFilters $filters, ?int $userId): void
    {
        $query
            // An exact-domain lookup, for a buyer who already knows the site.
            // Separate from search on purpose: the engine's fuzzy match on a
            // domain returns a dozen near misses, and this is the one field
            // where "did you mean" is not what was wanted.
            ->when($filters->domain, fn (Builder $q, string $domain) => $q->where('websites.domain', $domain))
            ->when($filters->categories !== [], fn (Builder $q) => $q->whereIn('websites.category_id', $filters->categories))
            ->when($filters->countries !== [], fn (Builder $q) => $q->whereIn('websites.country_id', $filters->countries))
            ->when($filters->languages !== [], fn (Builder $q) => $q->whereIn('websites.primary_language_id', $filters->languages))
            ->when($filters->price, fn (Builder $q, array $r) => $q->whereBetween('website_prices.price_cents', $r))
            ->when($filters->traffic, fn (Builder $q, array $r) => $q->whereBetween('metrics.monthly_traffic', $r))
            ->when($filters->dr, fn (Builder $q, array $r) => $q->whereBetween('metrics.ahrefs_dr', $r))
            ->when($filters->da, fn (Builder $q, array $r) => $q->whereBetween('metrics.moz_da', $r))
            ->when($filters->maxSpam !== null, fn (Builder $q) => $q->where('metrics.spam_score', '<=', $filters->maxSpam))
            ->when($filters->linkType, fn (Builder $q, string $type) => $q->where('websites.link_type', $type))
            // Traffic data means a figure that was measured, and zero is a
            // figure. A site with no metric row at all is the one this excludes.
            ->when($filters->hasTrafficData, fn (Builder $q) => $q->where('metrics.monthly_traffic', '>', 0));

        $this->constrainSpeeds($query, $filters);
        $this->constrainTopics($query, $filters);
        $this->constrainLists($query, $filters, $userId);
    }

    /**
     * @param  Builder<Website>  $query
     */
    private function constrainSpeeds(Builder $query, CatalogFilters $filters): void
    {
        if ($filters->speeds === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($filters): void {
            foreach ($filters->speeds as $value) {
                $speed = PublicationSpeed::tryFrom($value);

                if ($speed === null) {
                    continue;
                }

                [$from, $to] = $speed->hours();

                // Half-open bands, so a site at exactly 72 hours belongs to
                // one band and not to two. Ticking two boxes means either.
                $outer->orWhere(function (Builder $band) use ($from, $to): void {
                    $band->where('websites.publication_period_hours', $from === 0 ? '>=' : '>', $from);

                    if ($to !== null) {
                        $band->where('websites.publication_period_hours', '<=', $to);
                    }
                });
            }
        });
    }

    /**
     * Sites that accept every topic asked for.
     *
     * AND, not OR: a project that needs gambling *and* crypto accepted needs a
     * publisher who takes both, and a site that takes one of them is not a
     * partial answer — it is a site the order would be rejected on.
     *
     * @param  Builder<Website>  $query
     */
    private function constrainTopics(Builder $query, CatalogFilters $filters): void
    {
        foreach ($filters->topics as $slug) {
            // JSON containment, spelled the way both supported drivers read it.
            $query->whereJsonContains('websites.accepts_sensitive_topics', $slug);
        }
    }

    /**
     * The three filters that are about this advertiser rather than the site.
     *
     * @param  Builder<Website>  $query
     */
    private function constrainLists(Builder $query, CatalogFilters $filters, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        if ($filters->hideBlacklisted) {
            $query->notBlacklistedBy($userId);
        }

        if ($filters->onlyFavorites) {
            $query->whereExists(fn ($sub) => $sub->selectRaw('1')
                ->from('favorites')
                ->whereColumn('favorites.website_id', 'websites.id')
                ->where('favorites.user_id', $userId));
        }

        // "Not yet used in this project" is only meaningful inside one, and in
        // browse mode there is no project for it to mean anything about.
        if ($filters->notUsedInProject && $filters->projectId !== null) {
            $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('posts')
                ->whereColumn('posts.website_id', 'websites.id')
                ->where('posts.project_id', $filters->projectId)
                ->whereNull('posts.deleted_at'));
        }
    }

    /**
     * @param  Builder<Website>  $query
     * @return Builder<Website>
     */
    private function order(Builder $query, CatalogFilters $filters): Builder
    {
        match ($filters->sort) {
            'price_asc' => $query->orderBy('sort_price'),
            'price_desc' => $query->orderByDesc('sort_price'),
            'traffic' => $query->orderByDesc('sort_traffic'),
            'dr' => $query->orderByDesc('sort_dr'),
            'newest' => $query->orderByDesc('websites.id'),
            // Relevance with no search term is not a ranking of anything, so it
            // falls back to the catalog's own idea of what to show first.
            default => $query->orderByDesc('websites.is_featured')->orderByDesc('sort_traffic'),
        };

        // The tiebreak that makes the order total. Without it two sites with
        // the same traffic can swap places between pages, and the cursor —
        // which is built from the ordering columns — would skip or repeat one.
        return $query->orderBy('websites.id');
    }
}
