<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Catalog\Models\Language;
use App\Domain\Catalog\Models\Website;
use App\Domain\Catalog\Models\WebsiteCategory;
use Illuminate\Support\Facades\Cache;

/**
 * The proof lines on the auth panel.
 *
 * Real counts from the catalog, cached for an hour. A hard-coded "412 sites"
 * would be a lie the moment the network changed size, and the whole claim these
 * lines make is that we know exactly what we own.
 */
final class NetworkStats
{
    /**
     * @return list<string>
     */
    public function proofLines(): array
    {
        /** @var array{sites: int, niches: int, languages: int} $counts */
        $counts = Cache::remember('auth:network-stats', now()->addHour(), fn (): array => [
            'sites' => Website::query()->where('is_active', true)->count(),
            'niches' => WebsiteCategory::query()
                ->whereHas('websites', fn ($q) => $q->where('is_active', true))
                ->count(),
            'languages' => Language::query()
                ->whereHas('websites', fn ($q) => $q->where('is_active', true))
                ->count(),
        ]);

        return [
            "{$counts['sites']} sites · {$counts['niches']} niches · all owner-operated",
            "Published in {$counts['languages']} languages, on a schedule we set ourselves",
            'Traffic and domain rating re-measured every month, with the history kept',
            'Replacement or refund if a link comes down inside 12 months',
        ];
    }
}
