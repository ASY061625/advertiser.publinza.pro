<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Website;
use App\Domain\Trading\Enums\ServiceType;
use Illuminate\Support\Facades\Cache;

/**
 * Flattened catalog rows for the public marketing site.
 *
 * Cached, because the marketing home page is the LCP-critical page and must not
 * pay for a join across websites, metrics and prices on every visit. The cache
 * is short enough that a newly approved site appears the same day.
 */
final class GetCatalogPreview
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(int $limit = 8, ?string $categorySlug = null, ?string $search = null): array
    {
        $key = sprintf('marketing:catalog:%d:%s:%s', $limit, $categorySlug ?? 'all', md5($search ?? ''));

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember($key, now()->addMinutes(30), function () use ($limit, $categorySlug, $search): array {
            $query = Website::query()
                ->active()
                ->with(['category', 'primaryLanguage', 'latestMetric', 'prices'])
                ->joinSub(LatestWebsiteMetrics::query(), 'metrics', 'metrics.website_id', '=', 'websites.id')
                ->select('websites.*')
                // Strongest sites first: the preview should show the network at
                // its best, and a visitor scanning three rows reads the top.
                ->orderByDesc('metrics.monthly_traffic');

            if ($categorySlug !== null) {
                $query->whereHas('category', fn ($sub) => $sub->where('slug', $categorySlug));
            }

            if ($search !== null && $search !== '') {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('websites.domain', 'like', "%{$search}%")
                        ->orWhere('websites.title', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            }

            return $query->take($limit)->get()->map(fn (Website $website): array => [
                'domain' => $website->domain,
                'language' => $website->primaryLanguage?->code ?? '',
                'category' => $website->category?->name ?? '',
                'categorySlug' => $website->category?->slug ?? '',
                'traffic' => $website->latestMetric?->monthly_traffic ?? 0,
                'domainRating' => $website->latestMetric?->ahrefs_dr ?? 0,
                'priceCents' => $website->priceFor(ServiceType::ArticlePlacement)?->price_cents ?? 0,
            ])->all();
        });

        return $rows;
    }
}
