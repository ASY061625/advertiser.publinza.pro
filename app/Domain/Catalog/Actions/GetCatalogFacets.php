<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Models\Country;
use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\WebsiteCategory;

/**
 * The counts beside every checkbox, and the histogram behind the price slider.
 *
 * Each facet is counted against every filter *except its own*. That is what
 * makes a facet list usable: with Finance ticked, the Technology row should
 * still say how many sites you would get by ticking it too, not zero. Counting
 * a dimension against itself is the classic faceted-search bug — every
 * unselected option reads 0 and the list becomes a dead end.
 *
 * Four queries: three grouped counts and one histogram. They are grouped counts
 * over the same indexed join the results use, which is why this can run on
 * every keystroke of a filter change rather than being cached into staleness.
 */
final class GetCatalogFacets
{
    /** Bars behind the price track. Enough shape to aim at, few enough to read. */
    private const PRICE_BUCKETS = 24;

    public function __construct(private readonly SearchCatalog $search) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CatalogFilters $filters, ?int $userId, int $priceCeilingCents): array
    {
        return [
            'categories' => $this->named(
                WebsiteCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->toArray(),
                $this->countBy($filters->with(['categories' => []]), $userId, 'websites.category_id'),
            ),
            'countries' => $this->named(
                Country::query()->orderBy('name')->get(['id', 'name', 'code'])->toArray(),
                $this->countBy($filters->with(['countries' => []]), $userId, 'websites.country_id'),
                withCode: true,
            ),
            'languages' => $this->named(
                Language::query()->orderBy('name')->get(['id', 'name', 'code'])->toArray(),
                $this->countBy($filters->with(['languages' => []]), $userId, 'websites.primary_language_id'),
                withCode: true,
            ),
            'priceHistogram' => $this->priceHistogram($filters, $userId, $priceCeilingCents),
        ];
    }

    /**
     * One grouped count over the filtered catalog.
     *
     * @return array<int, int>
     */
    private function countBy(CatalogFilters $filters, ?int $userId, string $column): array
    {
        // Aliased rather than plucked as `count(*)`: pluck reads the value off
        // the row by the name it was selected under, and "count(*)" is not a
        // property any row has.
        return $this->search->query($filters, $userId, ordered: false)
            ->reorder()
            ->select($column)
            ->selectRaw('count(*) as tally')
            ->groupBy($column)
            ->pluck('tally', $column)
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, int>  $counts
     * @return list<array<string, mixed>>
     */
    private function named(array $rows, array $counts, bool $withCode = false): array
    {
        return array_values(array_map(static function (array $row) use ($counts, $withCode): array {
            $id = (int) $row['id'];

            return array_filter([
                'id' => $id,
                'name' => (string) $row['name'],
                'code' => $withCode ? (string) ($row['code'] ?? '') : null,
                // Always present, including zero. An option missing from the
                // list is indistinguishable from one that has not loaded; an
                // option reading 0 is information.
                'count' => $counts[$id] ?? 0,
            ], static fn (mixed $v): bool => $v !== null);
        }, $rows));
    }

    /**
     * The price distribution, as bar heights behind the slider track.
     *
     * Counted with the price filter itself removed, because the histogram is
     * what you aim the handles *at*. Drawing only the prices already inside the
     * selection would leave the bars outside it empty, which is the one thing
     * the picture exists to show.
     *
     * @return list<int>
     */
    private function priceHistogram(CatalogFilters $filters, ?int $userId, int $ceilingCents): array
    {
        $buckets = array_fill(0, self::PRICE_BUCKETS, 0);

        if ($ceilingCents <= 0) {
            return $buckets;
        }

        $size = max(1, (int) ceil($ceilingCents / self::PRICE_BUCKETS));

        $rows = $this->search->query($filters->with(['price' => null]), $userId, ordered: false)
            ->reorder()
            ->whereNotNull('website_prices.price_cents')
            // FLOOR over the two supported drivers. Integer `/` truncates on
            // SQLite and returns a decimal on MySQL, so dividing without it
            // would put a site in a different bar depending on the database.
            ->selectRaw("floor(website_prices.price_cents / {$size}) as bucket")
            ->selectRaw('count(*) as tally')
            ->groupBy('bucket')
            ->pluck('tally', 'bucket');

        foreach ($rows as $bucket => $count) {
            // The last bar carries everything above the ceiling, so a single
            // very expensive site cannot stretch the axis into a flat line.
            $index = min(self::PRICE_BUCKETS - 1, max(0, (int) $bucket));
            $buckets[$index] += (int) $count;
        }

        return $buckets;
    }
}
