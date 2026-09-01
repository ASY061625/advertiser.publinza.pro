<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Models\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchCatalog
{
    /**
     * @return LengthAwarePaginator<Website>
     */
    public function handle(CatalogFilters $filters, ?int $userId = null, int $perPage = 50): LengthAwarePaginator
    {
        // A text query goes through Meilisearch; everything else stays a plain
        // indexed query, which is cheaper and exactly consistent.
        //
        // Scout's builder has no getQuery(): it is not an Eloquent builder and
        // cannot be joined or filtered further. So the search runs first and
        // hands back ids, and the ids constrain an ordinary Eloquent query —
        // which is what the joins, scopes and ordering below all need.
        $query = Website::query();

        if ($filters->query !== null) {
            $ids = Website::search($filters->query)->take(1000)->keys();

            // An empty result set has to stay empty; whereIn on [] does that,
            // where omitting the clause would return the whole catalog.
            $query->whereIn('websites.id', $ids->all());
        }

        $query->active()
            ->with(['category', 'primaryLanguage', 'country', 'latestMetric', 'prices'])
            ->joinSub(LatestWebsiteMetrics::query(), 'metrics', 'metrics.website_id', '=', 'websites.id')
            ->leftJoin('website_prices', function ($join): void {
                $join->on('website_prices.website_id', '=', 'websites.id')
                    ->where('website_prices.service_type', '=', 'article_placement');
            })
            ->select('websites.*');

        if ($userId !== null) {
            $query->notBlacklistedBy($userId);
        }

        return $this->constrain($query, $filters)
            ->orderBy($this->sortColumn($filters->sort), $filters->direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<Website>  $query
     * @return Builder<Website>
     */
    private function constrain(Builder $query, CatalogFilters $filters): Builder
    {
        return $query
            ->when($filters->categories !== [], fn (Builder $q) => $q->whereIn('websites.category_id', $filters->categories))
            ->when($filters->language, fn (Builder $q, string $code) => $q->whereHas(
                'primaryLanguage',
                fn ($sub) => $sub->where('code', $code),
            ))
            ->when($filters->minTraffic, fn (Builder $q, int $v) => $q->where('metrics.monthly_traffic', '>=', $v))
            ->when($filters->maxPriceCents, fn (Builder $q, int $v) => $q->where('website_prices.price_cents', '<=', $v))
            ->when($filters->minDomainRating, fn (Builder $q, int $v) => $q->where('metrics.ahrefs_dr', '>=', $v))
            ->when($filters->maxSpamScore, fn (Builder $q, int $v) => $q->where('metrics.spam_score', '<=', $v));
    }

    /** Whitelisted so a sort parameter can never reach the query as raw SQL. */
    private function sortColumn(string $sort): string
    {
        return match ($sort) {
            'price' => 'website_prices.price_cents',
            'domain_rating' => 'metrics.ahrefs_dr',
            'domain_authority' => 'metrics.moz_da',
            'spam_score' => 'metrics.spam_score',
            default => 'metrics.monthly_traffic',
        };
    }
}
