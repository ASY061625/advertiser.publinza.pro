<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\DTOs\CatalogRanges;
use App\Domain\Catalog\Models\Site;
use Illuminate\Support\Facades\Cache;

final class GetCatalogRanges
{
    /**
     * The ranges move only when the catalog itself changes, so they are cached
     * rather than aggregated on every catalog request.
     */
    public function handle(): CatalogRanges
    {
        /** @var CatalogRanges $ranges */
        $ranges = Cache::remember('catalog:ranges', now()->addHour(), function (): CatalogRanges {
            /** @var object{traffic_min: int|null, traffic_max: int|null, dr_min: int|null, dr_max: int|null, da_min: int|null, da_max: int|null, spam_min: int|null, spam_max: int|null}|null $row */
            $row = Site::query()
                ->where('status', 'approved')
                ->selectRaw('MIN(traffic) traffic_min, MAX(traffic) traffic_max')
                ->selectRaw('MIN(domain_rating) dr_min, MAX(domain_rating) dr_max')
                ->selectRaw('MIN(domain_authority) da_min, MAX(domain_authority) da_max')
                ->selectRaw('MIN(spam_score) spam_min, MAX(spam_score) spam_max')
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
            );
        });

        return $ranges;
    }
}
