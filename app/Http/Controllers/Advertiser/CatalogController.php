<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advertiser;

use App\Domain\Catalog\Actions\GetCatalogRanges;
use App\Domain\Catalog\Actions\SearchCatalog;
use App\Domain\Catalog\DTOs\CatalogFilters;
use App\Domain\Catalog\Models\Website;
use App\Domain\Trading\Enums\ServiceType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(
        Request $request,
        SearchCatalog $searchCatalog,
        GetCatalogRanges $getCatalogRanges,
    ): Response {
        $websites = $searchCatalog->handle(
            CatalogFilters::fromRequest($request),
            $request->user()->id,
            (int) config('publinza.catalog.per_page', 50),
        );

        return inertia('Catalog/Index', [
            'sites' => $websites->through(fn (Website $website): array => $this->present($website)),
            // The quant-bars scale against the whole catalog, not this page.
            'ranges' => $getCatalogRanges->handle()->toArray(),
            'filters' => $request->only(['q', 'categories', 'language', 'min_traffic', 'max_price']),
        ]);
    }

    public function show(Website $website): Response
    {
        $this->authorize('view', $website);

        return inertia('Catalog/Show', [
            'site' => $this->present($website->load(['category', 'primaryLanguage', 'country', 'prices', 'latestMetric'])),
        ]);
    }

    /**
     * Flattens the site plus its newest metrics into the shape the catalog
     * table expects.
     *
     * @return array<string, mixed>
     */
    private function present(Website $website): array
    {
        $metric = $website->latestMetric;

        return [
            'id' => $website->id,
            'domain' => $website->domain,
            'language' => $website->primaryLanguage?->code ?? '',
            'category' => $website->category?->name ?? '',
            'priceMinorUnits' => $website->priceFor(ServiceType::ArticlePlacement)?->price_cents ?? 0,
            'traffic' => $metric?->monthly_traffic ?? 0,
            'domainRating' => $metric?->ahrefs_dr ?? 0,
            'domainAuthority' => $metric?->moz_da ?? 0,
            'spamScore' => $metric?->spam_score ?? 0,
        ];
    }
}
