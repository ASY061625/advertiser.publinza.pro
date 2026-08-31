<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchCatalog
{
    /**
     * @return LengthAwarePaginator<Site>
     */
    public function handle(CatalogFilters $filters, int $perPage = 50): LengthAwarePaginator
    {
        // A text query goes through Meilisearch; everything else is a plain
        // indexed query, which stays cheaper and exactly consistent.
        $query = $filters->query === null
            ? Site::query()->where('status', 'approved')
            : Site::search($filters->query)->query(fn (Builder $builder) => $builder->where('status', 'approved'))
                ->take(1000)
                ->getQuery();

        return $this->constrain($query, $filters)
            ->orderBy($filters->sort, $filters->direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    private function constrain(Builder $query, CatalogFilters $filters): Builder
    {
        return $query
            ->when($filters->categories !== [], fn (Builder $q) => $q->whereIn('category', $filters->categories))
            ->when($filters->language, fn (Builder $q, string $language) => $q->where('language', $language))
            ->when($filters->minTraffic, fn (Builder $q, int $value) => $q->where('traffic', '>=', $value))
            ->when($filters->maxPriceMinorUnits, fn (Builder $q, int $value) => $q->where('price_minor_units', '<=', $value))
            ->when($filters->minDomainRating, fn (Builder $q, int $value) => $q->where('domain_rating', '>=', $value))
            ->when($filters->maxSpamScore, fn (Builder $q, int $value) => $q->where('spam_score', '<=', $value));
    }
}
