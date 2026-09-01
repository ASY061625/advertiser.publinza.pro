<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogRanges;
use App\Domain\Catalog\Models\Website;
use Illuminate\Support\Facades\Cache;

/**
 * Min/max per metric across the whole active catalog.
 *
 * The catalog's quant-bars scale against this rather than the visible page —
 * scaling per page would rescale every bar on each pagination click and make
 * two pages incomparable. Cached because it only moves when the catalog does.
 */
final class GetCatalogRanges
{
    public function handle(): CatalogRanges
    {
        /** @var CatalogRanges $ranges */
        $ranges = Cache::remember(
            'catalog:ranges',
            now()->addMinutes((int) config('publinza.catalog.ranges_ttl_minutes', 60)),
            function (): CatalogRanges {
                /** @var object{traffic_min: int|null, traffic_max: int|null, dr_min: int|null, dr_max: int|null, da_min: int|null, da_max: int|null, spam_min: int|null, spam_max: int|null, price_min: int|null, price_max: int|null}|null $row */
                $row = Website::query()
                    ->active()
                    ->joinSub(
                        LatestWebsiteMetrics::query(),
                        'metrics',
                        'metrics.website_id',
                        '=',
                        'websites.id',
                    )
                    ->leftJoin('website_prices', function ($join): void {
                        $join->on('website_prices.website_id', '=', 'websites.id')
                            ->where('website_prices.service_type', '=', 'article_placement');
                    })
                    ->selectRaw('MIN(metrics.monthly_traffic) traffic_min, MAX(metrics.monthly_traffic) traffic_max')
                    ->selectRaw('MIN(metrics.ahrefs_dr) dr_min, MAX(metrics.ahrefs_dr) dr_max')
                    ->selectRaw('MIN(metrics.moz_da) da_min, MAX(metrics.moz_da) da_max')
                    ->selectRaw('MIN(metrics.spam_score) spam_min, MAX(metrics.spam_score) spam_max')
                    ->selectRaw('MIN(website_prices.price_cents) price_min, MAX(website_prices.price_cents) price_max')
                    ->first();

                return new CatalogRanges(
                    trafficMin: (int) ($row->traffic_min ?? 0),
                    trafficMax: (int) ($row->traffic_max ?? 0),
                    domainRatingMin: (int) ($row->dr_min ?? 0),
                    domainRatingMax: (int) ($row->dr_max ?? 100),
                    domainAuthorityMin: (int) ($row->da_min ?? 0),
                    domainAuthorityMax: (int) ($row->da_max ?? 100),
                    spamScoreMin: (int) ($row->spam_min ?? 0),
                    spamScoreMax: (int) ($row->spam_max ?? 100),
                    priceMinCents: (int) ($row->price_min ?? 0),
                    priceMaxCents: (int) ($row->price_max ?? 0),
                );
            },
        );

        return $ranges;
    }
}
